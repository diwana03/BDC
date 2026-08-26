<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\SchemaUpdater;

Auth::requireSuperAdmin();
$pdo = Database::connection();


$search = trim((string)($_GET['q'] ?? ''));
$keepId = (int)($_GET['keep_id'] ?? $_POST['keep_id'] ?? 0);
$mergeId = (int)($_GET['merge_id'] ?? $_POST['merge_id'] ?? 0);
$error = '';
$success = '';

function loadCompetitor(PDO $pdo, int $id): ?array
{
    if ($id <= 0) return null;
    $stmt = $pdo->prepare("SELECT c.*, COALESCE(SUM(p.points),0) total_points, COUNT(p.id) transaction_count
        FROM bdc_competitors c
        LEFT JOIN bdc_point_transactions p ON p.competitor_id=c.id
        WHERE c.id=:id GROUP BY c.id");
    $stmt->execute(['id'=>$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function loadEventOverlaps(PDO $pdo,int $keepId,int $mergeId): array
{
 if($keepId<1||$mergeId<1||$keepId===$mergeId)return [];
 $stmt=$pdo->prepare("SELECT k.id keep_transaction_id,d.id duplicate_transaction_id,k.event_id,k.division,k.dance_role,k.points keep_points,d.points duplicate_points,k.placement keep_placement,d.placement duplicate_placement,e.name event_name,e.event_date,(k.points=d.points AND COALESCE(k.placement,'')=COALESCE(d.placement,'')) exact_duplicate FROM bdc_point_transactions k JOIN bdc_point_transactions d ON d.event_id=k.event_id AND d.division=k.division AND d.dance_role=k.dance_role JOIN bdc_events e ON e.id=k.event_id WHERE k.competitor_id=:keep_id AND d.competitor_id=:merge_id ORDER BY e.event_date DESC,k.id,d.id");
 $stmt->execute(['keep_id'=>$keepId,'merge_id'=>$mergeId]);return $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::verify((string)($_POST['_csrf'] ?? ''));
    if ($keepId <= 0 || $mergeId <= 0 || $keepId === $mergeId) {
        $error = 'Choose two different competitor records.';
    } elseif ((string)($_POST['confirm_name'] ?? '') !== 'MERGE') {
        $error = 'Type MERGE to confirm.';
    } else {
        $keep = loadCompetitor($pdo, $keepId);
        $duplicate = loadCompetitor($pdo, $mergeId);
        if (!$keep || !$duplicate) {
            $error = 'One of the selected competitors no longer exists.';
        } else {
            try {
                $overlaps=loadEventOverlaps($pdo,$keepId,$mergeId);
                if($overlaps&&!isset($_POST['overlap_reviewed']))throw new RuntimeException('Review and acknowledge the overlapping event points before merging.');
                $pdo->beginTransaction();
                $removeIds=array_values(array_unique(array_map('intval',(array)($_POST['remove_duplicate_transaction']??[]))));
                if($removeIds){
                    $marks=implode(',',array_fill(0,count($removeIds),'?'));
                    $params=array_merge([$mergeId],$removeIds);
                    $pdo->prepare("DELETE FROM bdc_participant_results WHERE point_transaction_id IN (SELECT id FROM bdc_point_transactions WHERE competitor_id=? AND id IN ($marks))")->execute($params);
                    $pdo->prepare("DELETE FROM bdc_point_transactions WHERE competitor_id=? AND id IN ($marks)")->execute($params);
                }
                $tables = [
                    'bdc_claims',
                    'bdc_event_registrations',
                    'bdc_participant_results',
                    'bdc_point_adjustment_requests',
                    'bdc_point_transactions',
                    'bdc_profile_requests',
                    'bdc_form_sync_submissions',
                ];
                $moved = [];
                foreach ($tables as $table) {
                    $stmt = $pdo->prepare("UPDATE {$table} SET competitor_id=:keep_id WHERE competitor_id=:merge_id");
                    $stmt->execute(['keep_id'=>$keepId,'merge_id'=>$mergeId]);
                    $moved[$table] = $stmt->rowCount();
                }

                // Career progression and Special Competition Category are
                // separate profile axes. Preserve both before removing the
                // duplicate identity.
                $profileMerge=$pdo->prepare("INSERT INTO bdc_competitor_discipline_profiles(competitor_id,dance_style,dance_role,current_division,special_category) SELECT :keep_id,dance_style,dance_role,current_division,special_category FROM bdc_competitor_discipline_profiles WHERE competitor_id=:merge_id ON DUPLICATE KEY UPDATE dance_role=IF(dance_role='unknown',VALUES(dance_role),dance_role),current_division=IF(current_division IN('unknown','novice'),VALUES(current_division),current_division),special_category=COALESCE(special_category,VALUES(special_category)),updated_at=NOW()");
                $profileMerge->execute(['keep_id'=>$keepId,'merge_id'=>$mergeId]);
                $moved['bdc_competitor_discipline_profiles']=$profileMerge->rowCount();
                $pdo->prepare('DELETE FROM bdc_competitor_discipline_profiles WHERE competitor_id=:merge_id')->execute(['merge_id'=>$mergeId]);
                $specialMerge=$pdo->prepare("INSERT IGNORE INTO bdc_competitor_special_categories(competitor_id,dance_style,category,source_kind,source_name) SELECT :keep_id,dance_style,category,source_kind,source_name FROM bdc_competitor_special_categories WHERE competitor_id=:merge_id");
                $specialMerge->execute(['keep_id'=>$keepId,'merge_id'=>$mergeId]);
                $moved['bdc_competitor_special_categories']=$specialMerge->rowCount();
                $pdo->prepare('DELETE FROM bdc_competitor_special_categories WHERE competitor_id=:merge_id')->execute(['merge_id'=>$mergeId]);

                // Preserve useful profile data when the kept profile is blank or unknown.
                $stmt = $pdo->prepare("UPDATE bdc_competitors k JOIN bdc_competitors d ON d.id=:merge_id
                    SET k.country=COALESCE(NULLIF(TRIM(k.country),''),d.country),
                        k.instagram=COALESCE(NULLIF(TRIM(k.instagram),''),d.instagram),
                        k.email=COALESCE(NULLIF(TRIM(k.email),''),d.email),
                        k.phone=COALESCE(NULLIF(TRIM(k.phone),''),d.phone),
                        k.photo_url=COALESCE(NULLIF(TRIM(k.photo_url),''),d.photo_url),
                        k.dance_role=IF(k.dance_role='unknown',d.dance_role,k.dance_role),
                        k.current_division=IF(k.current_division='unknown',d.current_division,k.current_division),
                        k.career_group_id=COALESCE(k.career_group_id,d.career_group_id),
                        k.admin_notes=TRIM(CONCAT(COALESCE(k.admin_notes,''), '\nMerged duplicate ', d.bdc_id, ' / ', d.exact_name, ' on ', NOW()))
                    WHERE k.id=:keep_id");
                $stmt->execute(['keep_id'=>$keepId,'merge_id'=>$mergeId]);

                Auth::audit((int)Auth::user()['id'], 'competitor_merged', [
                    'kept'=>['id'=>$keepId,'bdc_id'=>$keep['bdc_id'],'name'=>$keep['exact_name']],
                    'removed'=>['id'=>$mergeId,'bdc_id'=>$duplicate['bdc_id'],'name'=>$duplicate['exact_name']],
                    'moved_rows'=>$moved,
                    'removed_duplicate_transaction_ids'=>$removeIds,
                    'event_overlap_count'=>count($overlaps),
                ], 'competitor', $keepId);

                $delete = $pdo->prepare('DELETE FROM bdc_competitors WHERE id=:id');
                $delete->execute(['id'=>$mergeId]);
                $pdo->commit();
                header('Location: merge.php?merged=1&keep_id=' . $keepId);
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Merge failed: ' . $e->getMessage();
            }
        }
    }
}

if (isset($_GET['merged'])) $success = 'Competitors merged successfully.';

$results = [];
if ($search !== '') {
    $stmt = $pdo->prepare("SELECT c.id,c.bdc_id,c.exact_name,c.country,c.dance_role,c.current_division,c.status,
        COALESCE(SUM(p.points),0) total_points
        FROM bdc_competitors c LEFT JOIN bdc_point_transactions p ON p.competitor_id=c.id
        WHERE c.exact_name LIKE :name OR c.bdc_id LIKE :bdc OR c.email LIKE :email OR c.instagram LIKE :instagram
        GROUP BY c.id ORDER BY c.exact_name LIMIT 50");
    $value = '%' . $search . '%';
    $stmt->execute(['name'=>$value,'bdc'=>$value,'email'=>$value,'instagram'=>$value]);
    $results = $stmt->fetchAll();
}
$keep = loadCompetitor($pdo, $keepId);
$duplicate = loadCompetitor($pdo, $mergeId);
$eventOverlaps=loadEventOverlaps($pdo,$keepId,$mergeId);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Merge Competitors | BDC Admin</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="<?=e(url('admin/'))?>">BDC Admin</a><a class="btn btn-outline-light btn-sm" href="./">Competitors</a></div></nav>
<main class="container py-4" style="max-width:1100px"><div class="mb-4"><h1 class="h3 mb-1">Merge Duplicate Competitors</h1><p class="text-muted mb-0">Keep one BDC record and move all linked history from the duplicate into it.</p></div>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><?php if($success):?><div class="alert alert-success"><?=e($success)?></div><?php endif;?>
<div class="alert alert-warning"><strong>Permanent action.</strong> The duplicate competitor record will be deleted after all linked rows are moved. Take a database backup first.</div>
<form class="card border-0 shadow-sm mb-4" method="get"><div class="card-body"><label class="form-label">Search competitors</label><div class="input-group"><input class="form-control" name="q" value="<?=e($search)?>" placeholder="Name, BDC ID, email or Instagram"><button class="btn btn-dark">Search</button></div></div></form>
<?php if($results):?><div class="card border-0 shadow-sm mb-4"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>BDC ID</th><th>Name</th><th>Country</th><th>Role</th><th>Division</th><th>Points</th><th>Choose</th></tr></thead><tbody><?php foreach($results as $r):?><tr><td><code><?=e($r['bdc_id'])?></code></td><td><?=e($r['exact_name'])?></td><td><?=e($r['country']?:'—')?></td><td><?=e(ucfirst($r['dance_role']))?></td><td><?=e(ucwords(str_replace('_',' ',$r['current_division'])))?></td><td><?=e((string)(float)$r['total_points'])?></td><td class="text-nowrap"><a class="btn btn-sm btn-success" href="?q=<?=urlencode($search)?>&keep_id=<?=(int)$r['id']?>&merge_id=<?=$mergeId?>">Keep</a> <a class="btn btn-sm btn-outline-danger" href="?q=<?=urlencode($search)?>&keep_id=<?=$keepId?>&merge_id=<?=(int)$r['id']?>">Duplicate</a></td></tr><?php endforeach;?></tbody></table></div></div><?php endif;?>
<div class="row g-3 mb-4"><div class="col-md-6"><div class="card h-100 border-success"><div class="card-header bg-success-subtle fw-semibold">Record to keep</div><div class="card-body"><?php if($keep):?><h2 class="h5"><?=e($keep['exact_name'])?></h2><div><code><?=e($keep['bdc_id'])?></code></div><div class="mt-2">Points: <strong><?=e((string)(float)$keep['total_points'])?></strong>, Transactions: <?= (int)$keep['transaction_count'] ?></div><a class="btn btn-sm btn-outline-dark mt-3" href="edit.php?id=<?=$keepId?>" target="_blank">Open profile</a><?php else:?><span class="text-muted">Not selected</span><?php endif;?></div></div></div>
<div class="col-md-6"><div class="card h-100 border-danger"><div class="card-header bg-danger-subtle fw-semibold">Duplicate to remove</div><div class="card-body"><?php if($duplicate):?><h2 class="h5"><?=e($duplicate['exact_name'])?></h2><div><code><?=e($duplicate['bdc_id'])?></code></div><div class="mt-2">Points: <strong><?=e((string)(float)$duplicate['total_points'])?></strong>, Transactions: <?= (int)$duplicate['transaction_count'] ?></div><a class="btn btn-sm btn-outline-dark mt-3" href="edit.php?id=<?=$mergeId?>" target="_blank">Open profile</a><?php else:?><span class="text-muted">Not selected</span><?php endif;?></div></div></div></div>
<?php if($keep && $duplicate && $keepId!==$mergeId):?><form method="post" class="card border-danger shadow-sm"><div class="card-body"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="keep_id" value="<?=$keepId?>"><input type="hidden" name="merge_id" value="<?=$mergeId?>"><h2 class="h5">Confirm merge</h2><p>All claims, event registrations, competition results, point transactions and profile requests belonging to <strong><?=e($duplicate['exact_name'])?></strong> will move to <strong><?=e($keep['exact_name'])?></strong>.</p>
<?php if($eventOverlaps):?><div class="alert alert-danger"><strong>Event duplicate finder found <?=count($eventOverlaps)?> overlapping point pair(s).</strong> Nothing is removed automatically. Select only confirmed duplicate rows; leave legitimate appended points unselected.</div><div class="table-responsive mb-3"><table class="table table-sm align-middle"><thead><tr><th>Event</th><th>Division, Role</th><th>Kept record</th><th>Duplicate record</th><th>Finding</th><th>Remove duplicate row</th></tr></thead><tbody><?php foreach($eventOverlaps as $o):?><tr><td><?=e($o['event_name'])?><br><small><?=e($o['event_date'])?></small></td><td><?=e(ucfirst($o['division']).', '.ucfirst($o['dance_role']))?></td><td><?=e((string)(float)$o['keep_points'])?> pts, <?=e($o['keep_placement']?:'no placement')?></td><td><?=e((string)(float)$o['duplicate_points'])?> pts, <?=e($o['duplicate_placement']?:'no placement')?></td><td><span class="badge <?=$o['exact_duplicate']?'text-bg-danger':'text-bg-warning'?>"><?=$o['exact_duplicate']?'Probable duplicate':'Different values'?></span></td><td><input class="form-check-input" type="checkbox" name="remove_duplicate_transaction[]" value="<?=(int)$o['duplicate_transaction_id']?>" <?=$o['exact_duplicate']?'checked':''?>></td></tr><?php endforeach;?></tbody></table></div><label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="overlap_reviewed" value="1" required><span class="form-check-label">I reviewed every overlapping event row. Unselected rows are legitimate points and should be preserved.</span></label><?php endif;?>
<label class="form-label">Type <strong>MERGE</strong></label><input class="form-control mb-3" name="confirm_name" autocomplete="off" required><button class="btn btn-danger" onclick="return confirm('This permanently deletes the duplicate competitor after the reviewed event-point decisions. Continue?')">Merge competitors permanently</button></div></form><?php endif;?>
</main></body></html>
