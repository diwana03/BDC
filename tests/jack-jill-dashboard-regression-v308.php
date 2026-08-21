<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$live = file_get_contents($root . '/admin/scoring/index.php');
$test = file_get_contents($root . '/admin/scoring-tests/select-mode.php');
$sidebar = file_get_contents($root . '/app/Views/admin/_sidebar_nav.php');
$dashboard = file_get_contents($root . '/app/Views/admin/dashboard.php');

foreach (['Manual Scoring','Automatic Scoring','Live Projection','?mode=manual','?mode=automated','../live-screen/'] as $marker) {
    if (!str_contains((string)$live, $marker)) { fwrite(STDERR, "Live Jack & Jill dashboard missing: {$marker}\n"); exit(1); }
}
foreach (['Manual Scoring','Automatic Scoring','Live Screen Projector','?mode=manual','?mode=automated','../live-screen/test-index.php'] as $marker) {
    if (!str_contains((string)$test, $marker)) { fwrite(STDERR, "Test Jack & Jill dashboard missing: {$marker}\n"); exit(1); }
}
if (!str_contains((string)$sidebar, "'admin/scoring/'") || str_contains((string)$sidebar, "'admin/scoring/?mode=manual'")) {
    fwrite(STDERR, "Sidebar must open the Jack & Jill mode dashboard.\n"); exit(1);
}
if (!preg_match('/url\(\s*"admin\/scoring\/"\s*,?\s*\).*Jack &amp; Jill Scoring/s', (string)$dashboard)) {
    fwrite(STDERR, "Main dashboard must open the Jack & Jill mode dashboard.\n"); exit(1);
}
foreach ([$live,$test,$sidebar,$dashboard] as $content) {
    if (preg_match('/\b(?:DELETE|TRUNCATE|DROP)\s+(?:FROM\s+|TABLE\s+)?bdc_(?:test_)?scoring_/i', (string)$content)) {
        fwrite(STDERR, "Navigation repair must not delete scoring data.\n"); exit(1);
    }
}
echo "Jack & Jill dashboard regression checks passed.\n";
