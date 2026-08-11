<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\SchemaUpdater;

Auth::requirePermission('competitors.edit');
$pdo = Database::connection();


$error = '';
$success = '';
$status = (string)($_GET['status'] ?? 'pending');
$allowedStatuses = ['pending', 'under_review', 'more_info', 'approved', 'rejected', 'all'];
if (!in_array($status, $allowedStatuses, true)) $status = 'pending';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token. Refresh the page and try again.';
    } else {
        $requestId = (int)($_POST['request_id'] ?? 0);
        $action = (string)($_POST['action'] ?? '');
        $adminNotes = trim((string)($_POST['admin_notes'] ?? '')) ?: null;

        $stmt = $pdo->prepare('SELECT * FROM bdc_profile_requests WHERE id=:id FOR UPDATE');
        try {
            $pdo->beginTransaction();
            $stmt->execute(['id' => $requestId]);
            $request = $stmt->fetch();
            if (!$request) throw new RuntimeException('Profile request not found.');

            $userId = (int)(Auth::user()['id'] ?? 0);

            if ($action === 'approve') {
                if ($request['request_type'] === 'profile_update') {
                    $competitorId = (int)($request['competitor_id'] ?? 0);
                    if ($competitorId < 1) throw new RuntimeException('The request is not linked to a competitor.');

                    $beforeStmt = $pdo->prepare('SELECT * FROM bdc_competitors WHERE id=:id');
                    $beforeStmt->execute(['id' => $competitorId]);
                    $before = $beforeStmt->fetch();
                    if (!$before) throw new RuntimeException('Linked competitor not found.');

                    $normalisedName = mb_strtolower(trim(preg_replace('/[^\pL\pN]+/u', ' ', (string)$request['full_name']) ?? (string)$request['full_name']));
                    $update = $pdo->prepare("UPDATE bdc_competitors SET
                        exact_name=:name,
                        normalised_name=:normalised,
                        email=:email,
                        phone=:phone,
                        instagram=:instagram,
                        country=:country,
                        dance_role=:role,
                        photo_url=COALESCE(NULLIF(:photo,''),photo_url)
                        WHERE id=:id");
                    $update->execute([
                        'name' => $request['full_name'],
                        'normalised' => $normalisedName,
                        'email' => $request['email'] ?: null,
                        'phone' => $request['phone'] ?: null,
                        'instagram' => $request['instagram'] ?: null,
                        'country' => $request['country'] ?: null,
                        'role' => $request['dance_role'],
                        'photo' => $request['photo_url'] ?: '',
                        'id' => $competitorId,
                    ]);
                    Auth::audit($userId, 'profile_request_approved', ['request_id'=>$requestId,'before'=>$before], 'competitor', $competitorId);
                } else {
                    $normalisedName = mb_strtolower(trim(preg_replace('/[^\pL\pN]+/u', ' ', (string)$request['full_name']) ?? (string)$request['full_name']));
                    $insert = $pdo->prepare("INSERT INTO bdc_competitors
                        (exact_name,normalised_name,email,phone,instagram,country,dance_role,current_division,photo_url,status,show_on_leaderboard)
                        VALUES(:name,:normalised,:email,:phone,:instagram,:country,:role,:division,:photo,'active',1)");
                    $insert->execute([
                        'name'=>$request['full_name'], 'normalised'=>$normalisedName,
                        'email'=>$request['email'] ?: null, 'phone'=>$request['phone'] ?: null,
                        'instagram'=>$request['instagram'] ?: null, 'country'=>$request['country'] ?: null,
                        'role'=>$request['dance_role'], 'division'=>$request['current_division'],
                        'photo'=>$request['photo_url'] ?: null,
                    ]);
                    $competitorId = (int)$pdo->lastInsertId();
                    $bdcId = 'BDC-' . str_pad((string)$competitorId, 6, '0', STR_PAD_LEFT);
                    $pdo->prepare('UPDATE bdc_competitors SET bdc_id=:bdc WHERE id=:id')->execute(['bdc'=>$bdcId,'id'=>$competitorId]);
                    $pdo->prepare('UPDATE bdc_profile_requests SET competitor_id=:cid WHERE id=:id')->execute(['cid'=>$competitorId,'id'=>$requestId]);
                    Auth::audit($userId, 'new_competitor_request_approved', ['request_id'=>$requestId], 'competitor', $competitorId);
                }
                $newStatus = 'approved';
                $success = 'Profile request approved.';
            } elseif ($action === 'reject') {
                $newStatus = 'rejected';
                $success = 'Profile request rejected.';
            } elseif ($action === 'more_info') {
                $newStatus = 'more_info';
                $success = 'Request marked as requiring more information.';
            } elseif ($action === 'under_review') {
                $newStatus = 'under_review';
                $success = 'Request marked as under review.';
            } else {
                throw new RuntimeException('Invalid action.');
            }

            $pdo->prepare('UPDATE bdc_profile_requests SET status=:status,admin_notes=:notes,reviewed_by=:uid,reviewed_at=NOW() WHERE id=:id')
                ->execute(['status'=>$newStatus,'notes'=>$adminNotes,'uid'=>$userId,'id'=>$requestId]);
            Auth::audit($userId, 'profile_request_status_changed', ['request_id'=>$requestId,'status'=>$newStatus], 'profile_request', $requestId);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

$where = '';
$params = [];
if ($status !== 'all') { $where = 'WHERE r.status=:status'; $params['status'] = $status; }
$sql = "SELECT r.*,c.bdc_id,c.exact_name AS current_name,c.email AS current_email,c.phone AS current_phone,
               c.instagram AS current_instagram,c.country AS current_country,c.dance_role AS current_role,
               c.current_division AS current_division_value,c.photo_url AS current_photo
        FROM bdc_profile_requests r
        LEFT JOIN bdc_competitors c ON c.id=r.competitor_id
        $where
        ORDER BY FIELD(r.status,'pending','under_review','more_info','approved','rejected'),r.created_at DESC,r.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

function requestStatusClass(string $status): string {
    return match($status) {
        'approved' => 'success', 'rejected' => 'danger', 'more_info' => 'warning',
        'under_review' => 'info', default => 'secondary'
    };
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Profile Requests | BDC Admin</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?=e(url('public/assets/css/app.css'))?>" rel="stylesheet">
<style>.request-photo{width:64px;height:76px;object-fit:cover;border-radius:8px;border:1px solid #dee2e6}.change-old{color:#6c757d}.change-new{font-weight:700}</style></head>
<body class="bg-light"><nav class="navbar navbar-dark bg-dark"><div class="container"><a class="navbar-brand" href="<?=e(url('admin/'))?>">BDC Admin</a><a class="btn btn-outline-light btn-sm" href="<?=e(url('admin/competitors/'))?>">Competitors</a></div></nav>
<main class="container py-4"><div class="d-flex flex-wrap justify-content-between gap-2 align-items-center mb-4"><div><h1 class="h3 mb-1">Profile Requests</h1><p class="text-muted mb-0">Review new competitor registrations and requested profile updates.</p></div></div>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><?php if($success):?><div class="alert alert-success"><?=e($success)?></div><?php endif;?>
<div class="btn-group flex-wrap mb-4"><?php foreach(['pending'=>'Pending','under_review'=>'Under Review','more_info'=>'More Info','approved'=>'Approved','rejected'=>'Rejected','all'=>'All'] as $value=>$label):?><a class="btn <?=$status===$value?'btn-dark':'btn-outline-dark'?>" href="?status=<?=e($value)?>"><?=e($label)?></a><?php endforeach;?></div>
<?php foreach($requests as $r):?>
<section class="card border-0 shadow-sm mb-3"><div class="card-header bg-white d-flex flex-wrap justify-content-between gap-2 align-items-center"><div><strong><?=e($r['full_name'])?></strong> <span class="badge text-bg-<?=requestStatusClass($r['status'])?>"><?=e(str_replace('_',' ',$r['status']))?></span><div class="small text-muted"><?=e(ucwords(str_replace('_',' ',$r['request_type'])))?> · Submitted <?=e($r['created_at'])?></div></div><?php if($r['competitor_id']):?><a class="btn btn-sm btn-outline-dark" href="<?=e(url('admin/competitors/edit.php?id='.(int)$r['competitor_id']))?>">Open competitor</a><?php endif;?></div>
<div class="card-body"><div class="row g-4"><div class="col-md-2 text-center"><img class="request-photo" src="<?=e($r['photo_url'] ?: $r['current_photo'] ?: url('public/assets/img/default-competitor.svg'))?>" alt="Profile photo"></div><div class="col-md-7"><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Field</th><th>Current</th><th>Requested</th></tr></thead><tbody>
<?php foreach([
'Name'=>[$r['current_name'],$r['full_name']], 'Email'=>[$r['current_email'],$r['email']], 'Phone'=>[$r['current_phone'],$r['phone']],
'Instagram'=>[$r['current_instagram'],$r['instagram']], 'Country'=>[$r['current_country'],$r['country']], 'Role'=>[$r['current_role'],$r['dance_role']],
'Division'=>[$r['current_division_value'],$r['current_division']]
] as $label=>$values):?><tr><th><?=e($label)?></th><td class="change-old"><?=e((string)($values[0] ?: '—'))?></td><td class="change-new"><?=e((string)($values[1] ?: '—'))?></td></tr><?php endforeach;?></tbody></table></div>
<?php if($r['notes']):?><div class="alert alert-light border mb-0"><strong>Competitor note:</strong> <?=nl2br(e($r['notes']))?></div><?php endif;?></div>
<div class="col-md-3"><form method="post"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="request_id" value="<?=(int)$r['id']?>"><label class="form-label">Admin notes</label><textarea class="form-control mb-3" name="admin_notes" rows="4"><?=e((string)$r['admin_notes'])?></textarea><div class="d-grid gap-2"><?php if(!in_array($r['status'],['approved','rejected'],true)):?><button class="btn btn-success" name="action" value="approve">Approve</button><button class="btn btn-outline-info" name="action" value="under_review">Under Review</button><button class="btn btn-outline-warning" name="action" value="more_info">Request More Info</button><button class="btn btn-outline-danger" name="action" value="reject" onclick="return confirm('Reject this request?')">Reject</button><?php else:?><div class="text-muted small">Reviewed <?=e((string)$r['reviewed_at'])?></div><?php endif;?></div></form></div></div></div></section>
<?php endforeach;?><?php if(!$requests):?><div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5">No profile requests found.</div></div><?php endif;?></main></body></html>
