<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;

final class InjuredFinalistRecoveryService
{
    private static function tables(bool $test): array
    {
        $prefix = $test ? 'bdc_test_scoring_' : 'bdc_scoring_';
        return [
            'rounds' => $prefix . 'rounds',
            'entries' => $prefix . 'entries',
            'results' => $prefix . 'results',
            'pairs' => $prefix . 'final_pairs',
            'marks' => $prefix . 'final_marks',
            'final_results' => $prefix . 'final_results',
            'sessions' => $prefix . 'judge_sessions',
            'audit' => $prefix . 'audit',
        ];
    }

    public static function recover(PDO $pdo, int $roundId, int $entryId, bool $test, int $userId, string $reason, string $confirmation, bool $promoteNext = true): array
    {
        if (strtoupper(trim($confirmation)) !== 'WITHDRAW FINALIST') throw new RuntimeException('Type WITHDRAW FINALIST to confirm the protected recovery.');
        $reason = trim($reason);
        $reasonLength = function_exists('mb_strlen') ? mb_strlen($reason) : strlen($reason);
        if ($reasonLength < 8) throw new RuntimeException('Enter a clear recovery reason of at least 8 characters.');
        if ($roundId < 1 || $entryId < 1) throw new RuntimeException('Select a valid injured finalist.');

        RandomPairingService::ensure($pdo);
        $t = self::tables($test);
        $pdo->beginTransaction();
        try {
            $roundStmt = $pdo->prepare("SELECT * FROM {$t['rounds']} WHERE id=:round FOR UPDATE");
            $roundStmt->execute(['round' => $roundId]);
            $round = $roundStmt->fetch();
            if (!$round || (string) $round['round_type'] !== 'final') throw new RuntimeException('Final round not found.');
            if (in_array((string) $round['status'], ['pending_approval', 'published', 'archived'], true)) throw new RuntimeException('Pending, published or archived Finals must be reopened through the protected publication workflow first.');

            $entryStmt = $pdo->prepare("SELECT * FROM {$t['entries']} WHERE id=:entry AND round_id=:round AND entry_status='active' FOR UPDATE");
            $entryStmt->execute(['entry' => $entryId, 'round' => $roundId]);
            $entry = $entryStmt->fetch();
            if (!$entry || !in_array((string) $entry['dance_role'], ['leader', 'follower'], true)) throw new RuntimeException('Active finalist not found.');

            $markStmt = $pdo->prepare("SELECT COUNT(*) FROM {$t['marks']} WHERE round_id=:round");
            $markStmt->execute(['round' => $roundId]);
            $clearedMarks = (int) $markStmt->fetchColumn();
            $resultStmt = $pdo->prepare("SELECT COUNT(*) FROM {$t['final_results']} WHERE round_id=:round");
            $resultStmt->execute(['round' => $roundId]);
            $clearedResults = (int) $resultStmt->fetchColumn();
            $pairStmt = $pdo->prepare("SELECT COUNT(*) FROM {$t['pairs']} WHERE round_id=:round");
            $pairStmt->execute(['round' => $roundId]);
            $clearedPairs = (int) $pairStmt->fetchColumn();
            $sessionStmt = $pdo->prepare("SELECT COUNT(*) FROM {$t['sessions']} WHERE round_id=:round AND status IN('scoring','submitted')");
            $sessionStmt->execute(['round' => $roundId]);
            $reopenedSessions = (int) $sessionStmt->fetchColumn();
            if ($clearedMarks + $clearedResults + $reopenedSessions < 1) throw new RuntimeException('This Final is not scoring-locked. Use the normal Finalist removal control instead.');

            $pdo->prepare("DELETE FROM {$t['final_results']} WHERE round_id=:round")->execute(['round' => $roundId]);
            $pdo->prepare("DELETE FROM {$t['marks']} WHERE round_id=:round")->execute(['round' => $roundId]);
            $pdo->prepare("DELETE FROM {$t['pairs']} WHERE round_id=:round")->execute(['round' => $roundId]);
            $pdo->prepare("UPDATE {$t['sessions']} SET status='not_started',opened_at=NULL,last_saved_at=NULL,submitted_at=NULL,unlocked_at=NOW(),unlocked_by=:user,unlock_reason=:reason WHERE round_id=:round")
                ->execute(['user' => $userId ?: null, 'reason' => $reason, 'round' => $roundId]);
            $pdo->prepare("UPDATE {$t['entries']} SET entry_status='withdrawn' WHERE id=:entry AND round_id=:round")
                ->execute(['entry' => $entryId, 'round' => $roundId]);

            $replacement = null;
            $sourceRoundId = (int) ($round['source_round_id'] ?: $round['parent_round_id']);
            if ($promoteNext && $sourceRoundId > 0) {
                $candidateStmt = $pdo->prepare("SELECT se.competitor_id,se.dance_role,se.bib_number,se.display_name,sr.rank_number,sr.total_score,sr.result_status FROM {$t['entries']} se JOIN {$t['results']} sr ON sr.round_id=se.round_id AND sr.entry_id=se.id WHERE se.round_id=:source AND se.dance_role=:role AND se.entry_status='active' AND NOT EXISTS(SELECT 1 FROM {$t['entries']} fe WHERE fe.round_id=:final AND fe.competitor_id=se.competitor_id AND fe.dance_role=se.dance_role) ORDER BY sr.rank_number ASC,sr.total_score DESC,se.bib_number ASC LIMIT 1");
                $candidateStmt->execute(['source' => $sourceRoundId, 'role' => $entry['dance_role'], 'final' => $roundId]);
                $replacement = $candidateStmt->fetch() ?: null;
                if ($replacement) {
                    $pdo->prepare("INSERT INTO {$t['entries']}(round_id,competitor_id,dance_role,bib_number,display_name,entry_status) VALUES(:round,:competitor,:role,:bib,:name,'active')")
                        ->execute(['round' => $roundId, 'competitor' => $replacement['competitor_id'], 'role' => $replacement['dance_role'], 'bib' => $replacement['bib_number'], 'name' => $replacement['display_name']]);
                }
            }

            $pdo->prepare("UPDATE {$t['rounds']} SET status='draft',locked_at=NULL,locked_by=NULL WHERE id=:round")->execute(['round' => $roundId]);
            $pdo->prepare("UPDATE bdc_pairing_presenter_links SET status='revoked' WHERE round_id=:round AND data_mode=:mode AND status='active'")->execute(['round' => $roundId, 'mode' => $test ? 'test' : 'real']);
            $pdo->prepare("UPDATE bdc_live_display_sessions SET results_unlocked=0,screen_type='holding',reveal_place=NULL,effect_type=NULL,loop_enabled=0,playlist_enabled=0,playlist_position=0,state_version=state_version+1,updated_by=:user,updated_at=NOW() WHERE data_mode=:mode AND is_enabled=1 AND (event_id=:primary_event OR active_event_id=:active_event)")
                ->execute(['user' => $userId ?: null, 'mode' => $test ? 'test' : 'real', 'primary_event' => (int) $round['event_id'], 'active_event' => (int) $round['event_id']]);

            $details = json_encode(['withdrawn_entry_id' => $entryId, 'withdrawn_name' => $entry['display_name'], 'role' => $entry['dance_role'], 'reason' => $reason, 'replacement_competitor_id' => $replacement ? (int) $replacement['competitor_id'] : null, 'replacement_name' => $replacement['display_name'] ?? null, 'replacement_source_rank' => $replacement ? (int) $replacement['rank_number'] : null, 'cleared_marks' => $clearedMarks, 'cleared_results' => $clearedResults, 'cleared_pairs' => $clearedPairs, 'reopened_judge_sessions' => $reopenedSessions, 'projector_returned_to_holding' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $pdo->prepare("INSERT INTO {$t['audit']}(round_id,user_id,action,details_json) VALUES(:round,:user,'injured_finalist_recovered',:details)")->execute(['round' => $roundId, 'user' => $userId ?: null, 'details' => $details]);
            $pdo->commit();

            return ['withdrawn' => (string) $entry['display_name'], 'replacement' => $replacement ? (string) $replacement['display_name'] : null, 'cleared_marks' => $clearedMarks, 'cleared_results' => $clearedResults, 'cleared_pairs' => $clearedPairs, 'reopened_judge_sessions' => $reopenedSessions];
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }
}
