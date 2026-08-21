<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$branding = file_get_contents($root . '/public/js/bdc-global-branding.js');
$liveResult = file_get_contents($root . '/admin/scoring/result.php');
$testResult = file_get_contents($root . '/admin/scoring-tests/result.php');
$liveAutomatic = file_get_contents($root . '/admin/scoring/automatic-round.php');
$testAutomatic = file_get_contents($root . '/admin/scoring-tests/automatic-inline.php');
$liveDashboard = file_get_contents($root . '/admin/scoring/core.php');
$testDashboard = file_get_contents($root . '/admin/scoring-tests/index.php');
$bootstrap = file_get_contents($root . '/bootstrap.php');

$checks = [
    'embedded logo protection' => str_contains($branding, 'if (existing && !brandTarget)'),
    'branding cache version' => str_contains($bootstrap, 'bdc-global-branding.js?v=327'),
    'Live null total protection' => str_contains($liveResult, "total_score']===null?'—'"),
    'Test null total protection' => str_contains($testResult, "total_score']===null?'—'"),
    'Live Automatic backup forms' => str_contains($liveAutomatic, 'Print Manual Backup Judge Forms') && str_contains($liveAutomatic, 'print.php?round_id='),
    'Test Automatic backup forms' => str_contains($testAutomatic, 'Print Manual Backup Judge Forms') && str_contains($testAutomatic, 'scoring-tests/print.php?round_id='),
    'Live Automatic Final backup form' => str_contains($liveDashboard, 'Print Final Judge Sheets'),
    'Test Automatic Final backup form' => str_contains($testDashboard, 'Print Final Judge Sheets'),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failed) {
    fwrite(STDERR, 'Print fallback v327 failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo "Print fallback v327 checks passed." . PHP_EOL;
