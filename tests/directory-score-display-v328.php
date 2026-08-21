<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$client = file_get_contents($root . '/public/js/dance-cup-directory.js');
$endpoint = file_get_contents($root . '/admin/dance-cup/directory-search.php');
$judges = file_get_contents($root . '/app/Services/JudgeDirectoryService.php');
$category = file_get_contents($root . '/admin/dance-cup/category.php');
$liveReport = file_get_contents($root . '/admin/scoring/result.php');
$testReport = file_get_contents($root . '/admin/scoring-tests/result.php');

$natural = "rtrim(rtrim(number_format((float)\$mark['weighted_score'],2,'.',''),'0'),'.')";
$checks = [
    'one-character client search' => str_contains($client, 'if(q.length<1)'),
    'one-character endpoint search' => str_contains($endpoint, 'mb_strlen($term) < 1'),
    'expanded competitor results' => str_contains($endpoint, 'LIMIT 100'),
    'expanded judge results' => str_contains($endpoint, 'search($pdo, $term, 100)'),
    'Judge Database supports first character' => str_contains($judges, 'mb_strlen($term)<1') && str_contains($judges, 'min(100,$limit)'),
    'compact left Bib field' => str_contains($category, 'dc-bib-field') && str_contains($client, 'flex:0 0 104px'),
    'directory asset cache bump' => str_contains($category, 'dance-cup-directory.js?v=328'),
    'Live natural score display' => str_contains($liveReport, $natural),
    'Test natural score display' => str_contains($testReport, $natural),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failed) {
    fwrite(STDERR, 'Directory/score display v328 failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo "Directory and score display v328 checks passed." . PHP_EOL;
