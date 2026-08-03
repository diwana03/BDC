<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\SchemaUpdater;

Auth::requireSuperAdmin();
$pdo = Database::connection();
SchemaUpdater::run($pdo);

$search = trim((string)($_GET['q'] ?? ''));
$keepId = (int)($_GET['keep_id'] ?? $_POST['keep_id'] ?? 0);
$mergeId = (int)($_GET['merge_id'] ?? $_POST['merge_id'] ?? 0);
$error = '';
$success = isset($_GET['merged']) ? 'Events merged successfully.' : '';

function loadEvent(PDO $pdo, int $id): ?array
{
    if ($id < 1) return null;
    $sql = "SELECT e.*,
        (SELECT COUNT(*) FROM bdc_point_transactions x WHERE x.event_id=e.id) point_rows,
        (SELECT COUNT(*) FROM bdc_participant_results x WHERE x.event_id=e.id) result_rows,
        (SELECT COUNT(*) FROM bdc_event_registrations x WHERE x.event_id=e.id) registration_rows,
        (SELECT COUNT(*) FROM bdc_result_documents x WHERE x.event_id=e.id) repository_rows,
        (SELECT COUNT(*) FROM bdc_event_ticket_types x WHERE x.event_id=e.id) ticket_rows,
        (SELECT COUNT(*) FROM bdc_event_points_tiers x WHERE x.event_id=e.id) tier_rows
        FROM bdc_events e WHERE e.id=:id";
    $s = $pdo->prepare($sql);
    $s->execute(['id' => $id]);
    return $s->fetch() ?: null;
}

function normaliseEventName(string $name): string
{
    $name = mb_strtolower($name);
    $name = str_replace(['corrected','correction','updated','update','singapore','sg'], '', $name);
    return preg_replace('/[^a-z0-9]+/u', '', $name) ?: '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify((string)($_POST['_csrf'] ?? ''));
    if ($keepId < 1 || $mergeId < 1 || $keepId === $mergeId) {
        $error = 'Choose two different event records.';
    } elseif ((string)($_POST['confirm_name'] ?? '') !== 'MERGE') {
        $error = 'Type MERGE to confirm.';
    } else {
        $keep = loadEvent($pdo, $keepId);
        $duplicate = loadEvent($pdo, $mergeId);
        if (!$keep || !$duplicate) {
            $error = 'One of the selected events no longer exists.';
        } else {
            try {
                $pdo->beginTransaction();
                $moved = [];

                // Preserve the kept event's tier settings. Copy only missing division/role tiers.
                $stmt = $pdo->prepare("INSERT IGNORE INTO bdc_event_points_tiers(event_id,division,dance_role,points_tier,created_at,updated_at)
                    SELECT :keep_id,division,dance_role,points_tier,created_at,updated_at
                    FROM bdc_event_points_tiers WHERE event_id=:merge_id");
                $stmt->execute(['keep_id'=>$keepId,'merge_id'=>$mergeId]);
                $moved['bdc_event_points_tiers_copied'] = $stmt->rowCount();
                $stmt = $pdo->prepare('DELETE FROM bdc_event_points_tiers WHERE event_id=:merge_id');
                $stmt->execute(['merge_id'=>$mergeId]);
                $moved['bdc_event_points_tiers_removed'] = $stmt->rowCount();

                // Tickets must move before registrations because registrations reference ticket IDs.
                $stmt = $pdo->prepare('UPDATE bdc_event_ticket_types SET event_id=:keep_id WHERE event_id=:merge_id');
                $stmt->execute(['keep_id'=>$keepId,'merge_id'=>$mergeId]);
                $moved['bdc_event_ticket_types'] = $stmt->rowCount();

                foreach ([
                    'bdc_event_registrations',
                    'bdc_point_transactions',
                    'bdc_participant_results',
                    'bdc_result_documents',
                    'bdc_documents',
                ] as $table) {
                    $stmt = $pdo->prepare("UPDATE {$table} SET event_id=:keep_id WHERE event_id=:merge_id");
                    $stmt->execute(['keep_id'=>$keepId,'merge_id'=>$mergeId]);
                    $moved[$table] = $stmt->rowCount();
                }

                Auth::audit((int)Auth::user()['id'], 'event_merged', [
                    'kept'=>['id'=>$keepId,'name'=>$keep['name'],'date'=>$keep['event_date']],
                    'removed'=>['id'=>$mergeId,'name'=>$duplicate['name'],'date'=>$duplicate['event_date']],
                    'moved_rows'=>$moved,
                ], 'event', $keepId);

                $stmt = $pdo->prepare('DELETE FROM bdc_events WHERE id=:id');
                $stmt->execute(['id'=>$mergeId]);
                $pdo->commit();
                header('Location: merge.php?merged=1&keep_id=' . $keepId);
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Merge failed and was rolled back: ' . $e->getMessage();
            }
        }
    }
}

$results = [];
if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = $pdo->prepare("SELECT e.*,
        (SELECT COUNT(*) FROM bdc_point_transactions x WHERE x.event_id=e.id) point_rows,
        (SELECT COUNT(*) FROM bdc_event_registrations x WHERE x.event_id=e.id) registration_rows,
        (SELECT COUNT(*) FROM bdc_result_documents x WHERE x.event_id=e.id) repository_rows
        FROM bdc_events e
        WHERE e.name LIKE :name OR CAST(e.id AS CHAR) LIKE :id OR DATE_FORMAT(e.event_date,'%Y-%m-%d') LIKE :event_date
        ORDER BY e.event_date DESC,e.name LIMIT 75");
    $stmt->execute(['name'=>$like,'id'=>$like,'event_date'=>$like]);
    $results = $stmt->fetchAll();
}

$suggestions = $pdo->query("SELECT e1.id id1,e1.name name1,e2.id id2,e2.name name2,e1.event_date
    FROM bdc_events e1 JOIN bdc_events e2 ON e1.id<e2.id AND e1.event_date=e2.event_date
    ORDER BY e1.event_date DESC,e1.id DESC LIMIT 30")->fetchAll();
$keep = loadEvent($pdo, $keepId);
$duplicate = loadEvent($pdo, $mergeId);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Merge Events | BDC Admin</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="<?=e(url('admin/'))?>">BDC Admin</a><div class="d-flex gap-2"><a class="btn btn-outline-light btn-sm" href="../competitors/merge.php">Merge Competitors</a><a class="btn btn-outline-light btn-sm" href="./">Events</a></div></div></nav>
<main class="container py-4" style="max-width:1150px">
<div class="d-flex flex-wrap gap-2 mb-4"><a class="btn btn-outline-dark" href="../competitors/merge.php">Merge Duplicate Competitors</a><a class="btn btn-dark" href="merge.php">Merge Duplicate Events</a></div>
<div class="mb-4"><h1 class="h3 mb-1">Merge Duplicate Events</h1><p class="text-muted mb-0">Keep one event and move all linked points, results, registrations, tickets and repository documents from the duplicate into it.</p></div>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><?php if($success):?><div class="alert alert-success"><?=e($success)?></div><?php endif;?>
<div class="alert alert-warning"><strong>Permanent action.</strong> The duplicate event will be deleted after linked rows are moved. The merge runs in a database transaction, but take a backup first.</div>
<form class="card border-0 shadow-sm mb-4" method="get"><div class="card-body"><label class="form-label">Search events</label><div class="input-group"><input class="form-control" name="q" value="<?=e($search)?>" placeholder="Event name, event ID or YYYY-MM-DD"><button class="btn btn-dark">Search</button></div></div></form>
<?php if($results):?><div class="card border-0 shadow-sm mb-4"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>ID</th><th>Event</th><th>Date</th><th>Points</th><th>Registrations</th><th>Documents</th><th>Choose</th></tr></thead><tbody><?php foreach($results as $r):?><tr><td><?= (int)$r['id'] ?></td><td><strong><?=e($r['name'])?></strong><div class="small text-muted"><?=e($r['venue']?:$r['location']?:'—')?></div></td><td><?=e((string)$r['event_date'])?></td><td><?= (int)$r['point_rows'] ?></td><td><?= (int)$r['registration_rows'] ?></td><td><?= (int)$r['repository_rows'] ?></td><td class="text-nowrap"><a class="btn btn-sm btn-success" href="?q=<?=urlencode($search)?>&keep_id=<?=(int)$r['id']?>&merge_id=<?=$mergeId?>">Keep</a> <a class="btn btn-sm btn-outline-danger" href="?q=<?=urlencode($search)?>&keep_id=<?=$keepId?>&merge_id=<?=(int)$r['id']?>">Duplicate</a></td></tr><?php endforeach;?></tbody></table></div></div><?php endif;?>
<?php if(!$results && $search===''):?><div class="card border-0 shadow-sm mb-4"><div class="card-body"><h2 class="h5">Possible duplicates on the same date</h2><?php if(!$suggestions):?><p class="text-muted mb-0">No same-date event pairs found.</p><?php else:?><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Date</th><th>Event 1</th><th>Event 2</th><th></th></tr></thead><tbody><?php foreach($suggestions as $s):?><tr><td><?=e((string)$s['event_date'])?></td><td>#<?=(int)$s['id1']?> <?=e($s['name1'])?></td><td>#<?=(int)$s['id2']?> <?=e($s['name2'])?></td><td><a class="btn btn-sm btn-outline-dark" href="?keep_id=<?=(int)$s['id1']?>&merge_id=<?=(int)$s['id2']?>">Review</a></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></div></div><?php endif;?>
<div class="row g-3 mb-4"><div class="col-md-6"><div class="card h-100 border-success"><div class="card-header bg-success-subtle fw-semibold">Event to keep</div><div class="card-body"><?php if($keep):?><h2 class="h5"><?=e($keep['name'])?></h2><div>ID <?= (int)$keep['id'] ?> · <?=e((string)$keep['event_date'])?></div><div class="small mt-2">Points: <?= (int)$keep['point_rows'] ?> · Results: <?= (int)$keep['result_rows'] ?> · Registrations: <?= (int)$keep['registration_rows'] ?> · Repository: <?= (int)$keep['repository_rows'] ?> · Tickets: <?= (int)$keep['ticket_rows'] ?> · Tiers: <?= (int)$keep['tier_rows'] ?></div><a class="btn btn-sm btn-outline-dark mt-3" href="index.php?edit=<?=$keepId?>" target="_blank">Open event</a><?php else:?><span class="text-muted">Not selected</span><?php endif;?></div></div></div>
<div class="col-md-6"><div class="card h-100 border-danger"><div class="card-header bg-danger-subtle fw-semibold">Duplicate to remove</div><div class="card-body"><?php if($duplicate):?><h2 class="h5"><?=e($duplicate['name'])?></h2><div>ID <?= (int)$duplicate['id'] ?> · <?=e((string)$duplicate['event_date'])?></div><div class="small mt-2">Points: <?= (int)$duplicate['point_rows'] ?> · Results: <?= (int)$duplicate['result_rows'] ?> · Registrations: <?= (int)$duplicate['registration_rows'] ?> · Repository: <?= (int)$duplicate['repository_rows'] ?> · Tickets: <?= (int)$duplicate['ticket_rows'] ?> · Tiers: <?= (int)$duplicate['tier_rows'] ?></div><a class="btn btn-sm btn-outline-dark mt-3" href="index.php?edit=<?=$mergeId?>" target="_blank">Open event</a><?php else:?><span class="text-muted">Not selected</span><?php endif;?></div></div></div></div>
<?php if($keep && $duplicate && $keepId!==$mergeId):?><form method="post" class="card border-danger shadow-sm"><div class="card-body"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="keep_id" value="<?=$keepId?>"><input type="hidden" name="merge_id" value="<?=$mergeId?>"><h2 class="h5">Confirm event merge</h2><p>All linked records from <strong><?=e($duplicate['name'])?></strong> will move to <strong><?=e($keep['name'])?></strong>. Existing tier settings on the kept event take priority.</p><?php if($keep['event_date']!==$duplicate['event_date']):?><div class="alert alert-danger">These events have different dates. Verify carefully before merging.</div><?php endif;?><label class="form-label">Type <strong>MERGE</strong></label><input class="form-control mb-3" name="confirm_name" autocomplete="off" required><button class="btn btn-danger" onclick="return confirm('This permanently deletes the duplicate event. Continue?')">Merge events permanently</button></div></form><?php endif;?>
</main></body></html>
