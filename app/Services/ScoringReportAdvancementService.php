<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class ScoringReportAdvancementService
{
    /**
     * Return the actual active roster advanced from this Heats/Semifinal round.
     * The child roster is authoritative because it includes approved manual
     * promotions and excludes automatic callbacks removed before the Final.
     *
     * @return array{round_id:int,round_type:string,entries:array<string,bool>}
     */
    public static function actualRoster(PDO $pdo, array $round, bool $test = false): array
    {
        $prefix = $test ? 'bdc_test_' : 'bdc_';
        $roundId = (int) ($round['id'] ?? 0);
        if ($roundId < 1 || (string) ($round['round_type'] ?? '') === 'final') {
            return ['round_id' => 0, 'round_type' => '', 'entries' => []];
        }

        $childStmt = $pdo->prepare("SELECT id,round_type
            FROM {$prefix}scoring_rounds
            WHERE event_id=:event
              AND division=:division
              AND dance_style=:dance
              AND (parent_round_id=:parent_round OR source_round_id=:source_round)
            ORDER BY CASE round_type WHEN 'final' THEN 1 WHEN 'semifinal' THEN 2 ELSE 3 END,id DESC
            LIMIT 1");
        $childStmt->execute([
            'event' => (int) ($round['event_id'] ?? 0),
            'division' => (string) ($round['division'] ?? ''),
            'dance' => (string) ($round['dance_style'] ?? 'bachata'),
            'parent_round' => $roundId,
            'source_round' => $roundId,
        ]);
        $child = $childStmt->fetch();
        if (!$child) {
            return ['round_id' => 0, 'round_type' => '', 'entries' => []];
        }

        $entryStmt = $pdo->prepare("SELECT competitor_id,dance_role
            FROM {$prefix}scoring_entries
            WHERE round_id=:round AND entry_status='active'");
        $entryStmt->execute(['round' => (int) $child['id']]);
        $entries = [];
        foreach ($entryStmt->fetchAll() as $entry) {
            $entries[self::key((int) $entry['competitor_id'], (string) $entry['dance_role'])] = true;
        }

        return [
            'round_id' => (int) $child['id'],
            'round_type' => (string) $child['round_type'],
            'entries' => $entries,
        ];
    }

    public static function annotate(array $entry, array $actualRoster): array
    {
        $hasActualRoster = (int) ($actualRoster['round_id'] ?? 0) > 0;
        $advanced = $hasActualRoster && !empty($actualRoster['entries'][self::key(
            (int) ($entry['competitor_id'] ?? 0),
            (string) ($entry['dance_role'] ?? '')
        )]);
        $roundType = (string) ($actualRoster['round_type'] ?? '');
        $entry['actual_advanced'] = $advanced;
        $entry['actual_advance_label'] = $advanced
            ? ($roundType === 'final' ? 'FINALIST' : 'SEMIFINALIST')
            : '';
        $entry['manual_promotion'] = $advanced && (string) ($entry['result_status'] ?? '') !== 'callback';
        $entry['not_advanced'] = $hasActualRoster && !$advanced && (string) ($entry['result_status'] ?? '') === 'callback';
        return $entry;
    }

    private static function key(int $competitorId, string $role): string
    {
        return $competitorId.':'.strtolower(trim($role));
    }
}
