<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Hard safety boundary for the Scoring Test Dashboard.
 *
 * Test mode may read production competitor/history/points data, but every write
 * must target an isolated bdc_test_* table. This service intentionally rejects
 * production points, progression, publication and scoring destinations.
 */
final class TestScoringGuardService
{
    private const ALLOWED_WRITE_PREFIX = 'bdc_test_';

    private const NEVER_WRITE = [
        'bdc_point_transactions',
        'bdc_participant_results',
        'bdc_scoring_rounds',
        'bdc_scoring_entries',
        'bdc_scoring_judges',
        'bdc_scoring_marks',
        'bdc_scoring_results',
        'bdc_scoring_final_pairs',
        'bdc_scoring_final_marks',
        'bdc_scoring_final_results',
        'bdc_scoring_publications',
        'bdc_scoring_publication_points',
        'bdc_competitors',
    ];

    public static function assertWriteTable(string $table): void
    {
        $table = strtolower(trim($table));
        if (in_array($table, self::NEVER_WRITE, true) || !str_starts_with($table, self::ALLOWED_WRITE_PREFIX)) {
            throw new RuntimeException('TEST SAFETY BLOCK: production table write rejected: ' . $table);
        }
    }

    public static function isTestTable(string $table): bool
    {
        return str_starts_with(strtolower(trim($table)), self::ALLOWED_WRITE_PREFIX);
    }

    public static function safetySummary(): array
    {
        return [
            'production_competitors' => 'read-only',
            'production_points' => 'read-only',
            'production_history' => 'read-only',
            'production_scoring' => 'blocked',
            'production_publication' => 'blocked',
            'test_tables' => 'read/write',
        ];
    }
}
