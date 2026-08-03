<?php
declare(strict_types=1);

/**
 * BDC v2.0.26 patch installer
 * Adds Heats/Semifinal live totals and a manual Calculate & Sort button.
 */

$root = __DIR__;
$target = $root . '/admin/scoring/index.php';
$sourceJs = $root . '/admin/scoring/heats-live-calculation-v226.js';

function fail(string $message): never {
    http_response_code(500);
    echo '<h1>BDC v2.0.26 installation failed</h1><pre>'
        . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
        . '</pre>';
    exit;
}

if (!is_file($target)) {
    fail('admin/scoring/index.php was not found. Upload this patch to the portal root.');
}

if (!is_file($sourceJs)) {
    fail('The v2.0.26 JavaScript file is missing.');
}

$php = file_get_contents($target);
if ($php === false) {
    fail('Unable to read admin/scoring/index.php.');
}

$marker = 'heats-live-calculation-v226.js';
if (str_contains($php, $marker)) {
    echo '<h1>BDC v2.0.26 already installed</h1>';
    exit;
}

$backupDir = $root . '/storage/patch-backups';
if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    fail('Unable to create storage/patch-backups.');
}

$backup = $backupDir . '/scoring-index-before-v226-' . date('Ymd-His') . '.php';
if (!copy($target, $backup)) {
    fail('Unable to create the scoring dashboard backup.');
}

$scriptTag = <<<'HTML'

<script src="<?=e(url('admin/scoring/heats-live-calculation-v226.js?v=226'))?>"></script>
HTML;

if (stripos($php, '</body>') !== false) {
    $patched = preg_replace('/<\/body>/i', $scriptTag . "\n</body>", $php, 1);
} else {
    $patched = $php . $scriptTag;
}

if (!is_string($patched) || $patched === $php) {
    fail('Unable to insert the v2.0.26 scoring script.');
}

if (file_put_contents($target, $patched) === false) {
    fail('Unable to write the patched scoring dashboard.');
}

echo '<!doctype html><html><head><meta charset="utf-8"><title>BDC v2.0.26 Installed</title>';
echo '<style>body{font-family:Arial;padding:40px;background:#f5f6f8}.box{max-width:760px;margin:auto;background:#fff;padding:28px;border-radius:12px;box-shadow:0 5px 20px #0001}.ok{color:#087830}</style>';
echo '</head><body><div class="box">';
echo '<h1 class="ok">BDC v2.0.26 installed</h1>';
echo '<p>Heats and Semifinal scoring now includes:</p>';
echo '<ul><li>Live totals while entering YES, A1, A2 and A3</li><li>Case-insensitive score entry</li><li>Calculate &amp; Sort Heats Results button</li><li>Duplicate A1/A2/A3 validation per judge</li><li>Separate Leader and Follower sorting</li></ul>';
echo '<p><strong>Backup:</strong> ' . htmlspecialchars($backup, ENT_QUOTES, 'UTF-8') . '</p>';
echo '<p><a href="admin/scoring/">Open Scoring Dashboard</a></p>';
echo '</div></body></html>';
