<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;

Auth::requireSuperAdmin();

const BDC_SCAN_FILE_LIMIT = 250000;
const BDC_SCAN_SECONDS = 20.0;

function bdcBytes(int|float $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $value = max(0, (float) $bytes);
    $unit = 0;
    while ($value >= 1024 && $unit < count($units) - 1) { $value /= 1024; $unit++; }
    return ($unit === 0 ? number_format($value, 0) : number_format($value, 2)) . ' ' . $units[$unit];
}

function bdcScanDirectory(string $path, string $label, float $deadline): array
{
    $result = ['label' => $label, 'path' => $path, 'bytes' => 0, 'files' => 0, 'old_files' => 0, 'temporary_bytes' => 0, 'largest' => [], 'complete' => true, 'available' => is_dir($path)];
    if (!$result['available']) return $result;

    try {
        $directory = new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO);
        $iterator = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD);
        foreach ($iterator as $file) {
            if (microtime(true) >= $deadline || $result['files'] >= BDC_SCAN_FILE_LIMIT) { $result['complete'] = false; break; }
            if (!$file instanceof SplFileInfo || !$file->isFile() || $file->isLink()) continue;
            $size = max(0, (int) $file->getSize());
            $result['bytes'] += $size;
            $result['files']++;
            if ($file->getMTime() < time() - 180 * 86400) $result['old_files']++;
            if (preg_match('/\.(tmp|part|partial)$/i', $file->getFilename())) $result['temporary_bytes'] += $size;
            $result['largest'][] = ['path' => $file->getPathname(), 'size' => $size, 'modified' => $file->getMTime()];
            usort($result['largest'], static fn(array $a, array $b): int => $b['size'] <=> $a['size']);
            if (count($result['largest']) > 12) array_pop($result['largest']);
        }
    } catch (Throwable $e) {
        $result['complete'] = false;
        $result['error'] = $e->getMessage();
    }
    return $result;
}

$appRoot = dirname(__DIR__, 2);
$accountRoot = dirname($appRoot);
$paths = [
    'Database backups' => $appRoot . '/storage/backups/database',
    'Website backups' => $appRoot . '/storage/backups/site',
    'Full backup packages' => $appRoot . '/storage/backups/full',
    'Deployment backups' => $appRoot . '/deployment_backups',
    'Account deployment backups' => $accountRoot . '/deployment_backups',
    'Competitor photos' => $appRoot . '/uploads/competitors',
    'Registration receipts' => $appRoot . '/storage/registration-receipts',
    'Application logs' => $appRoot . '/storage/logs',
    'Production results' => $accountRoot . '/.bdc-results/production',
    'Staging results' => $accountRoot . '/.bdc-results/staging',
    'Development repository' => $accountRoot . '/BDC_DEV/.git',
];

// Avoid counting the same resolved directory twice.
$uniquePaths = [];
foreach ($paths as $label => $path) {
    $key = realpath($path) ?: $path;
    if (!isset($uniquePaths[$key])) $uniquePaths[$key] = ['label' => $label, 'path' => $path];
}

$started = microtime(true);
$deadline = $started + BDC_SCAN_SECONDS;
$categories = [];
foreach ($uniquePaths as $item) $categories[] = bdcScanDirectory($item['path'], $item['label'], $deadline);
usort($categories, static fn(array $a, array $b): int => $b['bytes'] <=> $a['bytes']);

$databaseSize = 0;
try {
    $stmt = Database::connection()->prepare('SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.tables WHERE table_schema = :db');
    $stmt->execute(['db' => (string) Config::get('database.name')]);
    $databaseSize = (int) $stmt->fetchColumn();
} catch (Throwable) {}

$trackedBytes = $databaseSize + array_sum(array_column($categories, 'bytes'));
$diskTotal = (int) (disk_total_space($appRoot) ?: 0);
$diskFree = (int) (disk_free_space($appRoot) ?: 0);
$diskUsed = max(0, $diskTotal - $diskFree);
$scanComplete = !in_array(false, array_column($categories, 'complete'), true);

$largest = [];
foreach ($categories as $category) {
    foreach ($category['largest'] as $file) {
        $relative = str_replace('\\', '/', $file['path']);
        $relative = str_starts_with($relative, $accountRoot . '/') ? substr($relative, strlen($accountRoot) + 1) : basename($relative);
        $largest[] = $file + ['display_path' => $relative, 'category' => $category['label']];
    }
}
usort($largest, static fn(array $a, array $b): int => $b['size'] <=> $a['size']);
$largest = array_slice($largest, 0, 50);

$backupBytes = 0;
$deploymentBytes = 0;
foreach ($categories as $category) {
    if (str_contains(strtolower($category['label']), 'backup')) $backupBytes += $category['bytes'];
    if (str_contains(strtolower($category['label']), 'deployment backup')) $deploymentBytes += $category['bytes'];
}
$causes = [];
if ($backupBytes > 0) $causes[] = ['Backups', $backupBytes, 'Full backups retain a database file, website ZIP and another full ZIP containing both.'];
if ($deploymentBytes > 0) $causes[] = ['Deployment snapshots', $deploymentBytes, 'Deployment and rollback snapshots can accumulate separately from normal backups.'];
foreach (array_slice($categories, 0, 4) as $category) {
    if ($category['bytes'] > 0 && !str_contains(strtolower($category['label']), 'backup')) $causes[] = [$category['label'], $category['bytes'], number_format($category['files']) . ' files found.'];
}
usort($causes, static fn(array $a, array $b): int => $b[1] <=> $a[1]);

header('Cache-Control: no-store, private');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Storage Usage | BDC Admin</title>
<link rel="stylesheet" href="<?= e(url('public/assets/css/app.css?v=203')) ?>">
<style>
body{margin:0;background:#f4f6f8;color:#17212b;font-family:Inter,system-ui,sans-serif}.storage-wrap{max-width:1320px;margin:auto;padding:24px}.storage-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:22px}.storage-head h1{margin:0 0 6px}.storage-head p{margin:0;color:#667085}.back{background:#fff;border:1px solid #d0d5dd;border-radius:9px;padding:10px 14px;text-decoration:none;color:#17212b;font-weight:700}.metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.metric,.panel{background:#fff;border:1px solid #e4e7ec;border-radius:14px;box-shadow:0 2px 8px rgba(16,24,40,.04)}.metric{padding:18px}.metric span{display:block;color:#667085;font-size:13px}.metric strong{display:block;font-size:25px;margin:7px 0}.metric small{color:#98a2b3}.bar{height:9px;background:#eaecf0;border-radius:9px;overflow:hidden;margin-top:9px}.bar i{display:block;height:100%;background:#d92d20}.notice{margin:16px 0;padding:13px 16px;border-radius:10px;background:#fff4e5;border:1px solid #fec84b}.panel{margin-top:18px;padding:18px}.panel h2{margin:0 0 14px}.cause{display:grid;grid-template-columns:42px minmax(180px,1fr) auto;gap:12px;align-items:center;border-top:1px solid #eef0f2;padding:12px 0}.cause:first-of-type{border-top:0}.rank{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;background:#fff0eb;color:#b42318;font-weight:800}.cause p{margin:3px 0 0;color:#667085;font-size:13px}.table-wrap{overflow:auto}table{border-collapse:collapse;width:100%;font-size:14px}th,td{text-align:left;padding:11px 10px;border-bottom:1px solid #eaecf0;white-space:nowrap}th{color:#667085;font-size:12px;text-transform:uppercase}.muted{color:#98a2b3}.warn{color:#b54708;font-weight:700}.path{max-width:520px;overflow:hidden;text-overflow:ellipsis}.read-only{display:inline-block;background:#ecfdf3;color:#027a48;border-radius:20px;padding:5px 10px;font-size:12px;font-weight:800}@media(max-width:800px){.metrics{grid-template-columns:1fr 1fr}.storage-head{display:block}.back{display:inline-block;margin-top:12px}}@media(max-width:480px){.metrics{grid-template-columns:1fr}.storage-wrap{padding:14px}.cause{grid-template-columns:38px 1fr}.cause>strong:last-child{grid-column:2}}
</style>
</head>
<body><main class="storage-wrap">
<div class="storage-head"><div><span class="read-only">READ ONLY</span><h1>Storage Usage</h1><p>Live server diagnosis showing where hosting space is being consumed.</p></div><a class="back" href="<?= e(url('admin/')) ?>">← Admin Dashboard</a></div>
<section class="metrics">
 <div class="metric"><span>Server filesystem used</span><strong><?= e(bdcBytes($diskUsed)) ?></strong><small>of <?= e(bdcBytes($diskTotal)) ?></small><div class="bar"><i style="width:<?= $diskTotal > 0 ? min(100, round($diskUsed / $diskTotal * 100, 1)) : 0 ?>%"></i></div></div>
 <div class="metric"><span>Server filesystem free</span><strong><?= e(bdcBytes($diskFree)) ?></strong><small>Shared hosting disk value</small></div>
 <div class="metric"><span>BDC tracked storage</span><strong><?= e(bdcBytes($trackedBytes)) ?></strong><small>Selected folders plus database</small></div>
 <div class="metric"><span>Database</span><strong><?= e(bdcBytes($databaseSize)) ?></strong><small>Data and indexes</small></div>
</section>
<?php if(!$scanComplete):?><div class="notice"><strong>Partial scan:</strong> The safety limit was reached after <?= e(number_format(microtime(true)-$started,1)) ?> seconds. Displayed totals are minimum values; large folders may be bigger.</div><?php endif;?>

<section class="panel"><h2>Why storage grew quickly</h2>
<?php if(!$causes):?><p class="muted">No tracked storage was found. Check hosting-account usage in Bluehost for email and other websites.</p><?php else: foreach(array_slice($causes,0,6) as $index=>$cause):?>
 <div class="cause"><span class="rank"><?= $index+1 ?></span><div><strong><?= e($cause[0]) ?></strong><p><?= e($cause[2]) ?></p></div><strong><?= e(bdcBytes($cause[1])) ?></strong></div>
<?php endforeach; endif;?></section>

<section class="panel"><h2>Usage by location</h2><div class="table-wrap"><table><thead><tr><th>Location</th><th>Size</th><th>Files</th><th>Older than 180 days</th><th>Temporary files</th><th>Scan</th></tr></thead><tbody>
<tr><td>MySQL database</td><td><strong><?= e(bdcBytes($databaseSize)) ?></strong></td><td>—</td><td>—</td><td>—</td><td>Complete</td></tr>
<?php foreach($categories as $category):?><tr><td><?= e($category['label']) ?></td><td><strong><?= e(bdcBytes($category['bytes'])) ?></strong></td><td><?= e(number_format($category['files'])) ?></td><td><?= e(number_format($category['old_files'])) ?></td><td class="<?= $category['temporary_bytes']>0?'warn':'' ?>"><?= e(bdcBytes($category['temporary_bytes'])) ?></td><td class="<?= !$category['complete']?'warn':'' ?>"><?= !$category['available']?'Not found':($category['complete']?'Complete':'Partial') ?></td></tr><?php endforeach;?>
</tbody></table></div></section>

<section class="panel"><h2>Largest tracked files</h2><div class="table-wrap"><table><thead><tr><th>File</th><th>Category</th><th>Size</th><th>Last modified</th></tr></thead><tbody>
<?php if(!$largest):?><tr><td colspan="4" class="muted">No files found in tracked locations.</td></tr><?php else:foreach($largest as $file):?><tr><td class="path" title="<?= e($file['display_path']) ?>"><?= e($file['display_path']) ?></td><td><?= e($file['category']) ?></td><td><strong><?= e(bdcBytes($file['size'])) ?></strong></td><td><?= e(date('Y-m-d H:i',$file['modified'])) ?></td></tr><?php endforeach;endif;?>
</tbody></table></div></section>

<div class="notice"><strong>No files were changed.</strong> This report does not delete, move, compress, upload or alter backups. Bluehost quota may also include email, databases and other websites outside the BDC folders.</div>
</main></body></html>
