<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$service = file_get_contents($root . '/app/Services/RandomPairingService.php');
$live = file_get_contents($root . '/admin/scoring/core.php');
$test = file_get_contents($root . '/admin/scoring-tests/index.php');
$presenter = file_get_contents($root . '/pairing-presenter/index.php');
$projection = file_get_contents($root . '/admin/live-screen/control.php');

$checks = [
    'service blocks randomization after scoring starts' => str_contains($service, 'if(self::scoringStarted($p,$round,$test))'),
    'service requires REMATCH confirmation' => str_contains($service, "!=='REMATCH'"),
    'service clears invalid Final marks' => str_contains($service, 'DELETE FROM {$pre}scoring_final_marks'),
    'service clears invalid Final results' => str_contains($service, 'DELETE FROM {$pre}scoring_final_results'),
    'service reopens judge sessions' => str_contains($service, "status='not_started'"),
    'service revokes old Emcee access' => str_contains($service, "status='revoked'"),
    'live dashboard exposes authorised override' => str_contains($live, "action==='unlock_random_pairing'") && str_contains($live, 'Auth::canOverrideCompletedScores()'),
    'test dashboard mirrors authorised override' => str_contains($test, "action==='unlock_random_pairing'") && str_contains($test, 'Auth::canOverrideCompletedScores()'),
    'live dashboard disables Random Match' => str_contains($live, "'Random Match Locked'"),
    'test dashboard disables Random Match' => str_contains($test, "'Random Match Locked'"),
    'presenter uses protected service' => str_contains($presenter, 'RandomPairingService::randomize'),
    'projection controller uses protected link service' => str_contains($projection, 'RandomPairingService::generateLink'),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}
