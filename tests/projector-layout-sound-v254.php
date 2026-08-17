<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$control = file_get_contents($root . '/admin/live-screen/control.php');
$feed = file_get_contents($root . '/live-display/feed.php');
$sound = file_get_contents($root . '/live-display/effect-sound.js');
$presenter = file_get_contents($root . '/pairing-presenter/index.php');
$display = file_get_contents($root . '/live-display/index.php');

$checks = [
    'sound choice in control' => str_contains($control, 'Open Projector With Sound'),
    'muted choice in control' => str_contains($control, 'Open Muted'),
    'sound query generated' => str_contains($control, '&sound=1'),
    'no public sound button' => !str_contains($sound, 'createElement(\'button\')'),
    'sound cache refreshed' => str_contains($display, 'effect-sound.js?v=254'),
    'sound query respected' => str_contains($sound, "get('sound') === '1'"),
    'five card maximum' => str_contains($feed, '$coupleColumns = min(5'),
    'incomplete row expands' => str_contains($feed, 'display:flex;flex-wrap:wrap'),
    'bib remains larger' => str_contains($feed, 'font-size:clamp(19px,1.55vw,38px)'),
    'five second reveal' => str_contains($presenter, 'let seconds=5'),
    'projector countdown visual' => str_contains($display, 'function countdown()'),
    'blast reveal' => str_contains($presenter, "'champion_impact'"),
];
foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}
echo "OK: projector layout and sound control v254\n";
