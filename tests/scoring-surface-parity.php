<?php
declare(strict_types=1);

/**
 * Static release gate for the Test dashboard, Live dashboard, and public projector.
 *
 * This deliberately avoids a database connection so it can run in CI and before a
 * staging deployment. Runtime scoring fixtures remain covered by the existing
 * automatic and relative-placement tests.
 */

$root = dirname(__DIR__);
$failures = [];
$passes = [];

$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    $content = file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException('Unable to read ' . $relative);
    }
    return $content;
};

$requireMarkers = static function (
    string $surface,
    string $relative,
    array $markers
) use ($read, &$failures, &$passes): void {
    $content = $read($relative);
    foreach ($markers as $capability => $marker) {
        if (str_contains($content, $marker)) {
            $passes[] = $surface . ': ' . $capability;
        } else {
            $failures[] = $surface . ': missing ' . $capability . ' in ' . $relative;
        }
    }
};

$manualActions = [
    'round creation' => 'value="create_round"',
    'competitor assignment' => 'value="add_entry"',
    'competitor removal' => 'value="remove_entry"',
    'bib update' => 'value="update_bib"',
    'judge assignment' => 'value="save_judges"',
    'draft save' => 'value="save_scores"',
    'calculation' => 'value="calculate_scores"',
    'score submission' => 'value="submit_scores"',
    'callback tie resolution' => 'value="resolve_callback_tie"',
    'next-round creation' => 'value="create_next_round"',
    'final pairing' => 'value="save_final_pairing"',
    'final calculation' => 'value="calculate_final_ranking"',
    'final submission' => 'value="submit_final_scores"',
];
$requireMarkers('Test manual', 'admin/scoring-tests/index.php', $manualActions);
$requireMarkers('Live manual', 'admin/scoring/core.php', $manualActions);

$automaticWorkflow = [
    'draft save' => 'Save Scores Draft',
    'calculation' => 'Calculate &amp; Sort',
    'score submission' => 'Submit Scores',
    'completion state' => 'Scoring Completed',
    'print preview' => 'Preview / Print Scores',
    'callback handling' => 'Callback / Next Round',
    'judge progress' => 'Judge Progress',
    'submit readiness gate' => 'Waiting for All Judges',
    'split leaders' => 'LEADERS',
    'split followers' => 'FOLLOWERS',
];
$requireMarkers('Test automatic', 'admin/scoring-tests/automatic-inline.php', $automaticWorkflow);
$requireMarkers('Live automatic', 'admin/scoring/automatic-round.php', $automaticWorkflow);

$commonSetup = [
    'tier settings' => 'Save Tier Settings',
    'judge setup' => 'Submit Judges',
    'leader assignment' => 'Leaders',
    'follower assignment' => 'Followers',
    'registration link' => 'Registration Desk Link',
];
$requireMarkers('Test setup', 'admin/scoring-tests/index.php', $commonSetup);
$requireMarkers('Live setup', 'admin/scoring/automatic-common-setup.php', $commonSetup);

$reportCapabilities = [
    'automatic score formatting' => '$isAutomatic',
    'automatic average label' => "\$isAutomatic?'Average':'Total'",
    'print action' => 'window.print()',
    'leader report' => "'leader'=>'Leaders'",
    'follower report' => "'follower'=>'Followers'",
];
$requireMarkers('Test report', 'admin/scoring-tests/result.php', $reportCapabilities);
$requireMarkers('Live report', 'admin/scoring/result.php', $reportCapabilities);

$sharedProjector = [
    'Test and Live data mode' => '$test = $session["data_mode"] === "test"',
    'holding screen' => '$type === "holding"',
    'judge projection' => '$type === "judges"',
    'competitor projection' => '$type === "competitors"',
    'live heats scores' => '$type === "heats_scores"',
    'final results' => '"final_results"',
    'winner podium' => '$type === "winners"',
    'cute animal fallback' => 'projection-animals/rabbit.png',
    'one country display per contestant' => 'foreach ($people as $person)',
];
$requireMarkers('Shared projector', 'live-display/feed.php', $sharedProjector);

$projectorControl = [
    'Test and Live mode selection' => '$test = ($_GET["data_mode"]',
    'holding screen control' => 'data-screen="holding"',
    'judge screen control' => '"judges" => "Judges"',
    'competitor screen control' => '"competitors"',
    'scoring status control' => '"scoring" => "Scoring Status"',
    'results reveal lock' => 'Results Reveal Safety',
    'projector loop' => 'Projector Tab Loop',
    'effects' => 'Presentation Effects',
];
$requireMarkers('Shared projector control', 'admin/live-screen/control.php', $projectorControl);

$requireMarkers('Projector refresh', 'live-display/state.php', [
    'mark data version' => '$markTable',
    'final mark data version' => '$finalMarkTable',
    'result data version' => '$resultTable',
    'automatic refresh state' => '"data_version" => $dataVersion',
]);

$requireMarkers('Test judge links', 'app/Services/TestAutomaticJudgeService.php', [
    '12-hour expiry' => 'INTERVAL 12 HOUR',
    'criteria acceptance' => 'criteria_accepted_at',
]);
$requireMarkers('Live judge links', 'app/Services/AutomaticJudgeBrowserService.php', [
    '12-hour expiry' => 'INTERVAL 12 HOUR',
    'criteria acceptance' => 'criteria_accepted_at',
]);

$identicalPairs = [
    ['admin/scoring-tests/print.php', 'admin/scoring/print.php'],
    ['admin/scoring-tests/final-result.php', 'admin/scoring/final-result.php'],
    ['admin/scoring-tests/final-pdf.php', 'admin/scoring/final-pdf.php'],
    ['admin/scoring-tests/heats-pdf.php', 'admin/scoring/heats-pdf.php'],
    ['admin/scoring-tests/final-audit.php', 'admin/scoring/final-audit.php'],
];
foreach ($identicalPairs as [$testFile, $liveFile]) {
    $normalized = str_replace(
        ['bdc_test_', 'admin/scoring-tests/', 'scoring-tests'],
        ['bdc_', 'admin/scoring/', 'scoring'],
        $read($testFile)
    );
    if ($normalized === $read($liveFile)) {
        $passes[] = 'Exact normalized parity: ' . basename($liveFile);
    } else {
        $failures[] = 'Normalized Test/Live drift: ' . $testFile . ' <> ' . $liveFile;
    }
}

foreach ($passes as $pass) {
    fwrite(STDOUT, "PASS  {$pass}\n");
}
foreach ($failures as $failure) {
    fwrite(STDERR, "FAIL  {$failure}\n");
}

fwrite(STDOUT, sprintf("\nScoring parity: %d passed, %d failed.\n", count($passes), count($failures)));
exit($failures === [] ? 0 : 1);
