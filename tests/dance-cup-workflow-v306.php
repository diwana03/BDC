<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$landing = file_get_contents($root . '/admin/scoring/index.php');
$dashboard = file_get_contents($root . '/admin/dance-cup/index.php');
$service = file_get_contents($root . '/app/Services/DanceCupScoringService.php');
$testDashboard = file_get_contents($root . '/admin/scoring-tests/select-mode.php');
$migration = file_get_contents($root . '/database/migrations/20260821_1200_dance_cup_scoring_foundation.php');

foreach ([
    'Jack &amp; Jill' => $landing,
    'Dance Cup' => $landing,
    'criterion_name[]' => $dashboard,
    'criterion_max[]' => $dashboard,
    'bdc_dance_cup_competitions' => $service,
    'bdc_dance_cup_criteria' => $service,
    'bdc_test_dance_cup_competitions' => $migration,
    'bdc_test_dance_cup_criteria' => $migration,
    'data_mode=test' => $testDashboard,
] as $needle => $content) {
    if (!str_contains((string) $content, $needle)) {
        fwrite(STDERR, "Missing Dance Cup workflow marker: {$needle}\n");
        exit(1);
    }
}

if (str_contains($dashboard, 'DanceCupScoringService::ensure')) {
    fwrite(STDERR, "Dance Cup dashboard must not mutate schema during an HTTP request.\n");
    exit(1);
}

echo "Dance Cup workflow checks passed.\n";
