<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$control = file_get_contents($root . '/admin/live-screen/control.php');
$legacy = file_get_contents($root . '/admin/live-screen/pairing-link.php');
$feed = file_get_contents($root . '/live-display/feed.php');
$display = file_get_contents($root . '/live-display/index.php');
$service = file_get_contents($root . '/app/Services/RandomPairingService.php');
$liveScoring = file_get_contents($root . '/admin/scoring/core.php');
$testScoring = file_get_contents($root . '/admin/scoring-tests/index.php');

$checks = [
    'integrated Emcee action' => str_contains($control, 'generate_emcee'),
    'matching projector tab' => str_contains($control, '"matching" => "Emcee Live Matching"'),
    'one projector explanation' => str_contains($control, 'There is no second projector link.'),
    'active access retrieval' => str_contains($service, 'function activeLink'),
    'legacy redirect' => str_contains($legacy, "#emcee-match"),
    'live scoring integrated link' => str_contains($liveScoring, 'Event Projection &amp; Emcee Match'),
    'test scoring integrated link' => str_contains($testScoring, 'Test Event Projection &amp; Emcee Match'),
    'couple flag renderer' => str_contains($feed, 'country_flag_url($person["country"])'),
    'sound script loaded' => str_contains($display, 'effect-sound.js'),
    'sound implementation exists' => is_file($root . '/live-display/effect-sound.js'),
];
foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}
echo "OK: Emcee event-projection integration v252\n";
