<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$presenter = file_get_contents($root . '/pairing-presenter/index.php');
$feed = file_get_contents($root . '/live-display/feed.php');

$checks = [
    '30 second countdown' => str_contains($presenter, 'let seconds=30'),
    'automatic reveal action' => str_contains($presenter, 'name="action" value="reveal"'),
    'holding screen during countdown' => str_contains($presenter, "'screen_type'=>'holding'"),
    'matching screen after countdown' => str_contains($presenter, "'screen_type'=>'matching'"),
    'drum roll effect' => str_contains($presenter, "'drumroll'"),
    'repeat randomize disabled' => str_contains($presenter, "start.disabled=true"),
    'adaptive couple columns' => str_contains($feed, 'count($items) > 10 ? 4'),
    'large bib styling' => str_contains($feed, 'font-size:clamp(22px,2vw,48px)'),
    'flag renderer retained' => str_contains($feed, 'country_flag_url($person["country"])'),
];
foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}
echo "OK: timed random match reveal v253\n";
