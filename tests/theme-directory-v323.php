<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'theme controller' => file_get_contents($root . '/public/assets/js/bdc-theme.js'),
    'theme styles' => file_get_contents($root . '/public/assets/css/bdc-theme.css'),
    'Dance Cup directory client' => file_get_contents($root . '/public/js/dance-cup-directory.js'),
    'Dance Cup directory endpoint' => file_get_contents($root . '/admin/dance-cup/directory-search.php'),
    'Dance Cup category' => file_get_contents($root . '/admin/dance-cup/category.php'),
    'Live judge' => file_get_contents($root . '/judge-scoring/index.php'),
    'Test judge' => file_get_contents($root . '/test-judge-scoring/index.php'),
];
foreach ($files as $label => $content) {
    if ($content === false || $content === '') throw new RuntimeException($label . ' is missing.');
}
$checks = [
    'three appearance options' => str_contains($files['theme controller'], "['light', 'dark', 'system']"),
    'persistent preference' => str_contains($files['theme controller'], 'bdc-theme-preference'),
    'cross-tab sync' => str_contains($files['theme controller'], "BroadcastChannel('bdc-theme')"),
    'dark premium palette' => str_contains($files['theme styles'], '#0d1220') && str_contains($files['theme styles'], '#c9a45c'),
    'competitor directory' => str_contains($files['Dance Cup directory endpoint'], 'bdc_competitors'),
    'judge directory' => str_contains($files['Dance Cup directory endpoint'], 'JudgeDirectoryService::search'),
    'linked directory IDs' => str_contains($files['Dance Cup category'], 'competitor_id') && str_contains($files['Dance Cup category'], 'judge_id'),
    'directory duplicate guards' => str_contains($files['Dance Cup category'], 'already in this category') && str_contains($files['Dance Cup category'], 'already assigned to this category'),
    'Live bib-first review' => str_contains($files['Live judge'], 'review-bib'),
    'Test bib-first review' => str_contains($files['Test judge'], 'test-judge-heats-quotas-v188.js'),
];
foreach ($checks as $label => $passed) {
    if (!$passed) throw new RuntimeException('Failed: ' . $label);
}
echo "OK: shared theme, Dance Cup directory and bib-first Review Later v323\n";
