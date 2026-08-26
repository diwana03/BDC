<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$sidebar = file_get_contents($root . '/app/Views/admin/_sidebar_nav.php');
$dashboard = file_get_contents($root . '/app/Views/admin/dashboard.php');
$jackJill = file_get_contents($root . '/admin/scoring/index.php');
$testJackJill = file_get_contents($root . '/admin/scoring-tests/select-mode.php');

foreach ([
    '<summary>Jack &amp; Jill</summary>' => $sidebar,
    '<summary>Dance Cup</summary>' => $sidebar,
    'Jack & Jill Scoring' => $sidebar,
    'Dance Cup Scoring' => $sidebar,
    'admin/scoring/?mode=manual' => $sidebar,
    'admin/dance-cup/' => $sidebar,
    'Dance Cup Scoring Tests' => $sidebar,
    'data_mode=test' => $sidebar,
    "header('Location: ?mode=manual'" => $jackJill,
    'Jack &amp; Jill Scoring Tests' => $testJackJill,
] as $needle => $content) {
    if (!str_contains((string) $content, $needle)) {
        fwrite(STDERR, "Missing separate scoring navigation marker: {$needle}\n");
        exit(1);
    }
}

$jackGroup = strpos($sidebar, '<summary>Jack &amp; Jill</summary>');
$danceGroup = strpos($sidebar, '<summary>Dance Cup</summary>');
$jackLink = strpos($sidebar, "'Jack & Jill Scoring'");
$danceLink = strpos($sidebar, "'Dance Cup Scoring'");
if ($jackGroup === false || $danceGroup === false || $jackLink < $jackGroup || $jackLink > $danceGroup || $danceLink < $danceGroup) {
    fwrite(STDERR, "Jack & Jill and Dance Cup links must remain in separate sidebar groups.\n");
    exit(1);
}

if (str_contains($jackJill, 'Choose the competition scoring workflow.')) {
    fwrite(STDERR, "Jack & Jill must not show the shared workflow selector.\n");
    exit(1);
}
if (!str_contains($dashboard, 'Jack &amp; Jill Scoring') || !str_contains($dashboard, 'Dance Cup Scoring')) {
    fwrite(STDERR, "Legacy dashboard navigation is not in parity.\n");
    exit(1);
}

echo "Separate scoring navigation checks passed.\n";
