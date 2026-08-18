<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$testRoute = file_get_contents($root . '/admin/live-screen/test-projection.php');
$liveRoute = file_get_contents($root . '/admin/live-screen/projection.php');
$compat = file_get_contents($root . '/admin/live-screen/projection-compat.php');
$testControl = file_get_contents($root . '/admin/live-screen/test-control.php');
$feed = file_get_contents($root . '/live-display/feed.php');

$checks = [
    'legacy Test route delegates to shared compatibility route' => str_contains($testRoute, "require __DIR__ . '/projection-compat.php'"),
    'legacy Live route delegates to shared compatibility route' => str_contains($liveRoute, "require __DIR__ . '/projection-compat.php'"),
    'legacy public links redirect to shared live display' => str_contains($compat, "url('live-display/?token='"),
    'legacy admin links return to holding-safe shared control' => str_contains($compat, "'selection' => 1"),
    'Test control delegates to shared controller' => str_contains($testControl, 'control.php?data_mode=test'),
    'shared feed selects isolated Test and Live tables' => str_contains($feed, '$test ? "bdc_test_') && str_contains($feed, '$test = $session["data_mode"] === "test"'),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}
