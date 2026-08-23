<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$presenter = file_get_contents($root . '/pairing-presenter/index.php');
$feed = file_get_contents($root . '/live-display/feed.php');

$checks = [
    'five second countdown retained' => str_contains($presenter, 'let seconds=5'),
    'automatic reveal retained' => str_contains($presenter, 'id="autoReveal"'),
    'holding screen during countdown' => str_contains($presenter, "'screen_type'=>'holding'"),
    'matching screen after countdown' => str_contains($presenter, "'screen_type'=>'matching'"),
    'countdown effect retained' => str_contains($presenter, "'countdown'"),
    'no automatic blast reveal' => !str_contains($presenter, "'champion_impact'"),
    'four playful effects remain manual' => str_contains($presenter, "'hearts'=>") && str_contains($presenter, "'balloons'=>") && str_contains($presenter, "'heart_smiles'=>") && str_contains($presenter, "'finger_hearts'=>"),
    'fireworks removed from Emcee' => !str_contains($presenter, 'name="action" value="fireworks"'),
    'confetti removed from Emcee' => !str_contains($presenter, 'name="action" value="confetti"'),
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
echo "OK: timed random match reveal keeps countdown without automatic celebration effects\n";
