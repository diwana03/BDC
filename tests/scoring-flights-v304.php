<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$service = file_get_contents($root . '/app/Services/ScoringFlightService.php');
$panel = file_get_contents($root . '/admin/scoring/flights.php');
$projection = file_get_contents($root . '/live-display/feed.php');
$control = file_get_contents($root . '/admin/live-screen/control.php');

$checks = [
    'live and test tables' => str_contains($service, "'bdc_test_scoring_':'bdc_scoring_'"),
    'bib ordered entries' => str_contains($service, "ORDER BY dance_role,bib_number IS NULL,bib_number,id"),
    'final bib ordering' => str_contains($service, "ORDER BY l.bib_number IS NULL,l.bib_number,f.bib_number IS NULL,f.bib_number,p.id"),
    'scoring lock' => str_contains($service, 'Flight assignments are locked because scoring has started.'),
    'safety checkpoint' => str_contains($service, 'flight_rebuild'),
    'typed override' => str_contains($panel, "=== 'REBUILD'"),
    'optional wording' => str_contains($panel, 'Flights are optional.'),
    'final supported' => str_contains($panel, "round_type'] === 'final'"),
    'projection control' => str_contains($control, "['flights' => 'Flight Call']"),
    'projection feed' => str_contains($projection, 'FLIGHT {$flight} · NOW DANCING'),
];

$failed = array_keys(array_filter($checks, static fn(bool $ok): bool => !$ok));
if ($failed) {
    fwrite(STDERR, 'Failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}
echo 'Scoring Flights v304 checks passed.' . PHP_EOL;
