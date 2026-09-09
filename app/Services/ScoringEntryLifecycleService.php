<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;

final class ScoringEntryLifecycleService
{
    private const ENTRY_TABLES = [
        'bdc_scoring_entries',
        'bdc_test_scoring_entries',
    ];

    public static function restoreOrInsert(
        PDO $pdo,
        string $entries,
        int $roundId,
        int $competitorId,
        string $role,
        int $bib,
        string $displayName
    ): int {
        if (!in_array($entries, self::ENTRY_TABLES, true)) {
            throw new RuntimeException('Unsupported scoring entry table.');
        }
        if ($roundId < 1 || $competitorId < 1 || $bib < 1 || !in_array($role, ['leader', 'follower'], true)) {
            throw new RuntimeException('Invalid scoring competitor assignment.');
        }

        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
        $active = $pdo->prepare("SELECT id,bib_number,display_name FROM {$entries} WHERE round_id=:round AND competitor_id=:competitor AND dance_role=:role AND entry_status='active' LIMIT 1 FOR UPDATE");
        $active->execute(['round' => $roundId, 'competitor' => $competitorId, 'role' => $role]);
        if ($row = $active->fetch()) {
            throw new RuntimeException((string)$row['display_name'].' is already active as '.$role.' with bib '.(int)$row['bib_number'].'.');
        }

        $recoverable = $pdo->prepare("SELECT id FROM {$entries} WHERE round_id=:round AND competitor_id=:competitor AND dance_role=:role AND entry_status='withdrawn' ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $recoverable->execute(['round' => $roundId, 'competitor' => $competitorId, 'role' => $role]);
        $recoverableId = (int)($recoverable->fetchColumn() ?: 0);

        $occupied = $pdo->prepare("SELECT id,entry_status,display_name FROM {$entries} WHERE round_id=:round AND dance_role=:role AND bib_number=:bib LIMIT 1 FOR UPDATE");
        $occupied->execute(['round' => $roundId, 'role' => $role, 'bib' => $bib]);
        if ($row = $occupied->fetch()) {
            $occupiedId = (int)$row['id'];
            if ((string)$row['entry_status'] === 'active') {
                throw new RuntimeException('Bib '.$bib.' is already assigned to '.(string)$row['display_name'].'.');
            }
            if ($occupiedId !== $recoverableId) {
                $pdo->prepare("UPDATE {$entries} SET bib_number=1000000+id,updated_at=NOW() WHERE id=:id AND round_id=:round AND entry_status='withdrawn'")
                    ->execute(['id' => $occupiedId, 'round' => $roundId]);
            }
        }

        if ($recoverableId > 0) {
            $restore = $pdo->prepare("UPDATE {$entries} SET bib_number=:bib,display_name=:name,entry_status='active',updated_at=NOW() WHERE id=:id AND round_id=:round AND entry_status='withdrawn'");
            $restore->execute(['bib' => $bib, 'name' => $displayName, 'id' => $recoverableId, 'round' => $roundId]);
            if ($restore->rowCount() !== 1) {
                throw new RuntimeException('The withdrawn competitor changed while being restored. Refresh and try again.');
            }
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return $recoverableId;
        }

        $insert = $pdo->prepare("INSERT INTO {$entries}(round_id,competitor_id,dance_role,bib_number,display_name,entry_status) VALUES(:round,:competitor,:role,:bib,:name,'active')");
        $insert->execute(['round' => $roundId, 'competitor' => $competitorId, 'role' => $role, 'bib' => $bib, 'name' => $displayName]);
        $entryId = (int)$pdo->lastInsertId();
        if ($ownsTransaction) {
            $pdo->commit();
        }
        return $entryId;
        } catch (Throwable $error) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }
    }
}
