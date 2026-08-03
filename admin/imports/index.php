<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\CsvImportService;

Auth::requireSuperAdmin();

$templates = [
    'competitors' => ['file' => 'competitors.csv', 'headers' => ['exact_name','display_name','email','country','dance_role','status']],
    'events' => ['file' => 'events.csv', 'headers' => ['event_name','event_date','city','country','status']],
    'registrations' => ['file' => 'registrations.csv', 'headers' => ['event_name','event_date','competitor_exact_name','division','dance_role','registration_status']],
    'results' => ['file' => 'results.csv', 'headers' => ['event_name','event_date','competitor_exact_name','division','dance_role','placement','points']],
    'point-transactions' => ['file' => 'point-transactions.csv', 'headers' => ['event_name','event_date','competitor_exact_name','division','dance_role','points','reason']],
    'result-repository' => ['file' => 'result-repository.csv', 'headers' => ['event_name','event_date','document_type','title','external_url','published_status']],
];

if (isset($_GET['template'])) {
    $key = (string)$_GET['template'];
    if (!isset($templates[$key])) {
        http_response_code(404);
        exit('Unknown CSV template.');
    }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $templates[$key]['file'] . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $templates[$key]['headers']);
    fclose($out);
    exit;
}

$pdo = Database::connection();
$service = new CsvImportService($pdo);
$error = '';
$notice = '';
$preview = null;
$previewToken = $_SESSION['import_preview_token'] ?? null;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            throw new RuntimeException('Invalid security token. Refresh and try again.');
        }
        $action = (string)($_POST['action'] ?? 'preview');
        if ($action === 'preview') {
            if (!isset($_FILES['csv_file']) || !is_array($_FILES['csv_file'])) {
                throw new RuntimeException('Choose a CSV file.');
            }
            $file = $_FILES['csv_file'];
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Upload failed. Error code: ' . (int)($file['error'] ?? -1));
            }
            if ((int)$file['size'] > 10 * 1024 * 1024) {
                throw new RuntimeException('CSV must be smaller than 10 MB.');
            }
            $original = basename((string)$file['name']);
            if (strtolower(pathinfo($original, PATHINFO_EXTENSION)) !== 'csv') {
                throw new RuntimeException('Only .csv files are accepted.');
            }
            $token = bin2hex(random_bytes(16));
            $target = dirname(__DIR__, 2) . '/storage/imports/' . $token . '.csv';
            if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
                throw new RuntimeException('Unable to store uploaded file. Check storage/imports permissions.');
            }
            $preview = $service->inspect($target);
            $_SESSION['import_preview_token'] = $token;
            $_SESSION['import_preview_name'] = $original;
            $previewToken = $token;
        } elseif ($action === 'commit') {
            $token = preg_replace('/[^a-f0-9]/', '', (string)($_POST['preview_token'] ?? ''));
            if ($token === '' || !hash_equals((string)($_SESSION['import_preview_token'] ?? ''), $token)) {
                throw new RuntimeException('Import preview expired. Upload the CSV again.');
            }
            $path = dirname(__DIR__, 2) . '/storage/imports/' . $token . '.csv';
            if (!is_file($path)) {
                throw new RuntimeException('Uploaded CSV is no longer available.');
            }
            $result = $service->import($path, (string)($_SESSION['import_preview_name'] ?? 'upload.csv'), (int)Auth::user()['id']);
            @unlink($path);
            unset($_SESSION['import_preview_token'], $_SESSION['import_preview_name']);
            $previewToken = null;
            $notice = sprintf('Import #%d completed: %d imported, %d skipped, %d errors.', $result['batch_id'], $result['imported'], $result['skipped'], $result['errors']);
        } elseif ($action === 'rollback') {
            $batchId = (int)($_POST['batch_id'] ?? 0);
            $counts = $service->rollback($batchId, (int)Auth::user()['id']);
            $notice = sprintf('Import #%d rolled back: %d transactions, %d documents, %d competitors and %d events removed.', $batchId, $counts['transactions'], $counts['documents'], $counts['competitors'], $counts['events']);
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$batches = $pdo->query('SELECT id,file_name,import_type,status,total_rows,imported_rows,skipped_rows,error_rows,created_at,completed_at,rolled_back_at FROM bdc_import_batches ORDER BY id DESC LIMIT 25')->fetchAll();
$errorsByBatch = [];
if ($batches) {
    $ids = array_map(fn($b) => (int)$b['id'], $batches);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT batch_id,row_number,error_message FROM bdc_import_errors WHERE batch_id IN ($placeholders) ORDER BY id DESC");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) $errorsByBatch[(int)$row['batch_id']][] = $row;
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Legacy &amp; Bulk Import | BDC Competitor Dashboard</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light">
<nav class="navbar navbar-expand navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="../">BDC Admin</a><div class="text-white small">Hello, <?= e(Auth::user()['full_name'] ?? '') ?> <a class="btn btn-outline-light btn-sm ms-2" href="../?logout=1"></a> <a class="btn btn-warning btn-sm ms-2" href="https://bachatadancecouncil.com/" target="_blank">🏠 BDC Home</a> Logout</a></div></div></nav>
<div class="container py-4" style="max-width:1200px">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4"><div><h1 class="h3 mb-1">Legacy &amp; Bulk Import</h1><div class="text-muted">Super Admin only</div></div><a class="btn btn-outline-secondary" href="../">Back to dashboard</a></div>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if ($notice): ?><div class="alert alert-success"><?= e($notice) ?></div><?php endif; ?>
<div class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
<h2 class="h5">1. Upload legacy CSV</h2>
<p class="text-muted">Supported live imports: BDC Point Entry, Event Registration Form and BDC Result Entry. Every file is previewed before data is written.</p>
<form method="post" enctype="multipart/form-data" class="row g-3 align-items-end">
<?= Csrf::field() ?><input type="hidden" name="action" value="preview">
<div class="col-md-9"><label class="form-label" for="csv_file">CSV file</label><input class="form-control" type="file" id="csv_file" name="csv_file" accept=".csv,text/csv" required></div>
<div class="col-md-3 d-grid"><button class="btn btn-dark">Preview import</button></div>
</form></div></div>
<?php if ($preview): ?>
<div class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
<div class="d-flex flex-wrap justify-content-between gap-3"><div><h2 class="h5 mb-1">2. Review preview</h2><div class="text-muted">Detected type: <strong><?= e(ucfirst($preview['type'])) ?></strong>, <?= (int)$preview['total_rows'] ?> data rows</div></div>
<form method="post" onsubmit="return confirm('Import this CSV into the live BDC database?')"><?= Csrf::field() ?><input type="hidden" name="action" value="commit"><input type="hidden" name="preview_token" value="<?= e((string)$previewToken) ?>"><button class="btn btn-success">Confirm and import</button></form></div>
<div class="table-responsive mt-3"><table class="table table-sm table-striped align-middle"><thead><tr><?php foreach ($preview['headers'] as $header): ?><th><?= e($header) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach ($preview['rows'] as $row): ?><tr><?php foreach ($preview['headers'] as $header): ?><td class="text-nowrap"><?= e(mb_strimwidth((string)($row[$header] ?? ''),0,80,'…')) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div>
<div class="small text-muted">Showing the first <?= count($preview['rows']) ?> rows only. Aggregate point columns are retained for reference but are not imported as transactions, preventing double counting.</div>
</div></div>
<?php endif; ?>
<div class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
<h2 class="h5">Future CSV formats</h2>
<p class="text-muted">Download the approved column format before preparing future bulk data. Template downloads do not change the database. Import support for these formats can be enabled module by module after validation rules are approved.</p>
<div class="row g-2">
<?php foreach ($templates as $key => $template): ?>
<div class="col-sm-6 col-lg-4"><a class="btn btn-outline-primary w-100 text-start" href="?template=<?= e($key) ?>">Download <?= e($template['file']) ?></a></div>
<?php endforeach; ?>
</div>
<div class="alert alert-warning mt-3 mb-0 small">Do not upload future-format templates through the legacy importer unless the page identifies the file as a supported import type.</div>
</div></div>
<div class="card border-0 shadow-sm"><div class="card-body p-4"><h2 class="h5">Import history</h2>
<?php if (!$batches): ?><p class="text-muted mb-0">No imports yet.</p><?php else: ?><div class="table-responsive"><table class="table align-middle"><thead><tr><th>ID</th><th>File</th><th>Type</th><th>Status</th><th>Rows</th><th>Imported</th><th>Skipped</th><th>Errors</th><th>Date</th><th></th></tr></thead><tbody>
<?php foreach ($batches as $batch): ?><tr><td>#<?= (int)$batch['id'] ?></td><td><?= e($batch['file_name']) ?></td><td><?= e($batch['import_type']) ?></td><td><span class="badge text-bg-<?= $batch['status']==='completed'?'success':($batch['status']==='completed_with_errors'?'warning':'secondary') ?>"><?= e($batch['status']) ?></span><?php if ($batch['rolled_back_at']): ?><div class="small text-danger">Rolled back</div><?php endif; ?></td><td><?= (int)$batch['total_rows'] ?></td><td><?= (int)$batch['imported_rows'] ?></td><td><?= (int)$batch['skipped_rows'] ?></td><td><?= (int)$batch['error_rows'] ?><?php if (!empty($errorsByBatch[(int)$batch['id']])): ?><details><summary class="small">View</summary><?php foreach (array_slice($errorsByBatch[(int)$batch['id']],0,10) as $er): ?><div class="small text-danger">Row <?= (int)$er['row_number'] ?>: <?= e($er['error_message']) ?></div><?php endforeach; ?></details><?php endif; ?></td><td class="text-nowrap"><?= e($batch['created_at']) ?></td><td><?php if (!$batch['rolled_back_at'] && in_array($batch['status'],['completed','completed_with_errors'],true)): ?><form method="post" onsubmit="return confirm('Rollback this import? Imported transactions and documents will be removed. Newly created unused competitors and events will also be removed.')"><?= Csrf::field() ?><input type="hidden" name="action" value="rollback"><input type="hidden" name="batch_id" value="<?= (int)$batch['id'] ?>"><button class="btn btn-sm btn-outline-danger">Rollback</button></form><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></div></div>
</div></body></html>
