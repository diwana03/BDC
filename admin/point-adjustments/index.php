<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;

Auth::requirePermission('points.adjust.request');
$pdo=Database::connection();
$user=Auth::user();
$isSuper=Auth::isSuperAdmin();
$message='';$error='';

if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!Csrf::verify($_POST['_csrf']??null)){$error='Invalid security token. Refresh and try again.';}
 else try{
  $action=(string)($_POST['action']??'');
  if($action==='request'){
   $competitorId=(int)($_POST['competitor_id']??0);$eventId=(int)($_POST['event_id']??0);
   $division=(string)($_POST['division']??'');$role=(string)($_POST['dance_role']??'');
   $points=(float)($_POST['additional_points']??0);$reason=trim((string)($_POST['reason']??''));
   if($competitorId<1||$eventId<1||!in_array($division,['novice','intermediate','advanced','all_star'],true)||!in_array($role,['leader','follower'],true)||$points<=0||$reason==='')throw new RuntimeException('Contestant, event, division, role, positive points and reason are required.');
   $sum=$pdo->prepare('SELECT COALESCE(SUM(points),0) FROM bdc_point_transactions WHERE competitor_id=:c AND event_id=:e AND division=:d AND dance_role=:r');
   $sum->execute(['c'=>$competitorId,'e'=>$eventId,'d'=>$division,'r'=>$role]);$existing=(float)$sum->fetchColumn();
   $hash=hash('sha256',implode('|',[$competitorId,$eventId,$division,$role,number_format($points,2,'.',''),strtolower($reason),(int)$user['id']]));
   $stmt=$pdo->prepare("INSERT INTO bdc_point_adjustment_requests(competitor_id,event_id,division,dance_role,existing_event_points,additional_points,reason,requested_by,request_hash) VALUES(:c,:e,:d,:r,:existing,:points,:reason,:uid,:hash)");
   $stmt->execute(['c'=>$competitorId,'e'=>$eventId,'d'=>$division,'r'=>$role,'existing'=>$existing,'points'=>$points,'reason'=>$reason,'uid'=>$user['id'],'hash'=>$hash]);
   Auth::audit((int)$user['id'],'point_adjustment_requested',['event_id'=>$eventId,'division'=>$division,'dance_role'=>$role,'existing_points'=>$existing,'additional_points'=>$points],'competitor',$competitorId);
   $message='Point adjustment submitted for Super Admin approval. Official points were not changed.';
  }elseif(in_array($action,['approve','reject'],true)){
   if(!$isSuper)throw new RuntimeException('Only Super Admin can review point adjustments.');
   $id=(int)($_POST['request_id']??0);$reviewReason=trim((string)($_POST['review_reason']??''));
   $pdo->beginTransaction();
   $s=$pdo->prepare("SELECT * FROM bdc_point_adjustment_requests WHERE id=:id AND status='pending' FOR UPDATE");$s->execute(['id'=>$id]);$request=$s->fetch();
   if(!$request)throw new RuntimeException('This request is no longer pending.');
   if((int)$request['requested_by']===(int)$user['id'])throw new RuntimeException('You cannot approve or reject your own request.');
   if($action==='reject'){
    $pdo->prepare("UPDATE bdc_point_adjustment_requests SET status='rejected',reviewed_by=:u,reviewed_at=NOW(),review_reason=:reason WHERE id=:id")->execute(['u'=>$user['id'],'reason'=>$reviewReason,'id'=>$id]);
   }else{
    $dup=$pdo->prepare("SELECT id,points FROM bdc_point_transactions WHERE competitor_id=:c AND event_id=:e AND division=:d AND dance_role=:r AND points=:p AND source_type='correction' LIMIT 1 FOR UPDATE");
    $dup->execute(['c'=>$request['competitor_id'],'e'=>$request['event_id'],'d'=>$request['division'],'r'=>$request['dance_role'],'p'=>$request['additional_points']]);
    if($dup->fetch())throw new RuntimeException('An identical approved correction already exists for this contestant and event. Review Event Duplicates instead.');
    $hash=hash('sha256','point-adjustment|'.$id);
    $pdo->prepare("INSERT INTO bdc_point_transactions(competitor_id,event_id,division,dance_role,points,notes,source_type,source_row_hash,created_by) VALUES(:c,:e,:d,:r,:p,:notes,'correction',:hash,:u)")->execute(['c'=>$request['competitor_id'],'e'=>$request['event_id'],'d'=>$request['division'],'r'=>$request['dance_role'],'p'=>$request['additional_points'],'notes'=>'Approved point adjustment #'.$id.': '.$request['reason'],'hash'=>$hash,'u'=>$user['id']]);
    $tx=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO bdc_participant_results(event_id,competitor_id,division,dance_role,finalist_status,points_awarded,source,point_transaction_id) VALUES(:e,:c,:d,:r,'participant',:p,'manual',:tx)")->execute(['e'=>$request['event_id'],'c'=>$request['competitor_id'],'d'=>$request['division'],'r'=>$request['dance_role'],'p'=>$request['additional_points'],'tx'=>$tx]);
    $pdo->prepare("UPDATE bdc_point_adjustment_requests SET status='approved',reviewed_by=:u,reviewed_at=NOW(),review_reason=:reason,point_transaction_id=:tx WHERE id=:id")->execute(['u'=>$user['id'],'reason'=>$reviewReason,'tx'=>$tx,'id'=>$id]);
   }
   Auth::audit((int)$user['id'],'point_adjustment_'.$action,['request_id'=>$id,'review_reason'=>$reviewReason],'point_adjustment',$id);
   $pdo->commit();$message='Point adjustment '.$action.'d successfully.';
  }
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$error=$e->getMessage();}
}

$q=trim((string)($_GET['q']??''));$competitors=[];
if($q!==''){$s=$pdo->prepare("SELECT id,bdc_id,exact_name,country FROM bdc_competitors WHERE exact_name LIKE :q OR bdc_id LIKE :q ORDER BY exact_name LIMIT 30");$s->execute(['q'=>'%'.$q.'%']);$competitors=$s->fetchAll();}
$selectedCompetitor=(int)($_GET['competitor_id']??0);$selectedEvent=(int)($_GET['event_id']??0);
$events=$pdo->query("SELECT id,name,event_date FROM bdc_events ORDER BY COALESCE(event_date,'1900-01-01') DESC,id DESC")->fetchAll();
$eventPoints=[];
if($selectedCompetitor&&$selectedEvent){$s=$pdo->prepare("SELECT division,dance_role,SUM(points) total_points,COUNT(*) transaction_count FROM bdc_point_transactions WHERE competitor_id=:c AND event_id=:e GROUP BY division,dance_role ORDER BY division,dance_role");$s->execute(['c'=>$selectedCompetitor,'e'=>$selectedEvent]);$eventPoints=$s->fetchAll();}
$pending=$pdo->query("SELECT r.*,c.exact_name,c.bdc_id,e.name event_name,u.full_name requester_name FROM bdc_point_adjustment_requests r JOIN bdc_competitors c ON c.id=r.competitor_id JOIN bdc_events e ON e.id=r.event_id JOIN bdc_users u ON u.id=r.requested_by WHERE r.status='pending' ORDER BY r.requested_at")->fetchAll();
$history=$pdo->query("SELECT r.*,c.exact_name,e.name event_name,u.full_name requester_name,rv.full_name reviewer_name FROM bdc_point_adjustment_requests r JOIN bdc_competitors c ON c.id=r.competitor_id JOIN bdc_events e ON e.id=r.event_id JOIN bdc_users u ON u.id=r.requested_by LEFT JOIN bdc_users rv ON rv.id=r.reviewed_by WHERE r.status<>'pending' ORDER BY r.reviewed_at DESC LIMIT 50")->fetchAll();
$duplicates=$pdo->query("SELECT p.competitor_id,p.event_id,p.division,p.dance_role,COUNT(*) row_count,SUM(p.points) total_points,COUNT(DISTINCT CONCAT(p.points,'|',COALESCE(p.placement,''))) distinct_values,c.exact_name,c.bdc_id,e.name event_name,e.event_date FROM bdc_point_transactions p JOIN bdc_competitors c ON c.id=p.competitor_id JOIN bdc_events e ON e.id=p.event_id GROUP BY p.competitor_id,p.event_id,p.division,p.dance_role HAVING COUNT(*)>1 ORDER BY e.event_date DESC,c.exact_name LIMIT 100")->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Point Adjustments | BDC Admin</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="<?=e(url('admin/'))?>">BDC Admin</a><a class="btn btn-outline-light btn-sm" href="<?=e(url('admin/competitors/merge.php'))?>">Merge Duplicates</a></div></nav><main class="container py-4" style="max-width:1200px">
<h1 class="h3">Point Adjustments</h1><p class="text-muted">View a contestant's existing event points, append a missing amount, and submit it for Super Admin approval.</p>
<?php if($message):?><div class="alert alert-success"><?=e($message)?></div><?php endif;?><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
<div class="card shadow-sm border-0 mb-4"><div class="card-body"><form method="get" class="row g-2"><div class="col-md-8"><input class="form-control" name="q" value="<?=e($q)?>" placeholder="Search contestant name or BDC ID"></div><div class="col-md-4"><button class="btn btn-dark w-100">Search</button></div></form><?php if($competitors):?><div class="list-group mt-3"><?php foreach($competitors as $c):?><a class="list-group-item list-group-item-action" href="?q=<?=urlencode($q)?>&competitor_id=<?=$c['id']?>"><?=e($c['exact_name'])?>, <?=e($c['bdc_id'])?>, <?=e($c['country']?:'—')?></a><?php endforeach;?></div><?php endif;?></div></div>
<?php if($selectedCompetitor):?><div class="card shadow-sm border-0 mb-4"><div class="card-body"><form method="get" class="row g-2"><input type="hidden" name="competitor_id" value="<?=$selectedCompetitor?>"><div class="col-md-9"><select class="form-select" name="event_id" required><option value="">Select event</option><?php foreach($events as $e):?><option value="<?=$e['id']?>" <?=$selectedEvent===$e['id']?'selected':''?>><?=e($e['name'].' · '.($e['event_date']?:'Date pending'))?></option><?php endforeach;?></select></div><div class="col-md-3"><button class="btn btn-outline-dark w-100">Show Event Points</button></div></form><?php if($selectedEvent):?><hr><h2 class="h5">Existing points for this event</h2><?php if(!$eventPoints):?><p class="text-muted">No points recorded for this contestant at this event.</p><?php else:?><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Division</th><th>Role</th><th>Points</th><th>Records</th></tr></thead><tbody><?php foreach($eventPoints as $p):?><tr><td><?=e(ucfirst($p['division']))?></td><td><?=e(ucfirst($p['dance_role']))?></td><td><?=e((string)(float)$p['total_points'])?></td><td><?=e((string)$p['transaction_count'])?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?><form method="post" class="row g-3"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="action" value="request"><input type="hidden" name="competitor_id" value="<?=$selectedCompetitor?>"><input type="hidden" name="event_id" value="<?=$selectedEvent?>"><div class="col-md-3"><select class="form-select" name="division" required><option value="">Division</option><?php foreach(['novice','intermediate','advanced','all_star'] as $v):?><option value="<?=$v?>"><?=e(ucwords(str_replace('_',' ',$v)))?></option><?php endforeach;?></select></div><div class="col-md-3"><select class="form-select" name="dance_role" required><option value="">Role</option><option value="leader">Leader</option><option value="follower">Follower</option></select></div><div class="col-md-3"><input class="form-control" type="number" min="0.01" step="0.01" name="additional_points" placeholder="Points to append" required></div><div class="col-12"><textarea class="form-control" name="reason" placeholder="Required reason and source" required></textarea></div><div class="col-12"><button class="btn btn-primary">Submit for Approval</button></div></form><?php endif;?></div></div><?php endif;?>
<?php if($isSuper&&$pending):?><div class="card border-danger shadow-sm mb-4"><div class="card-header bg-danger text-white"><strong>Pending Super Admin Approval</strong></div><div class="card-body"><?php foreach($pending as $r):?><form method="post" class="border-bottom pb-3 mb-3"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="request_id" value="<?=$r['id']?>"><strong><?=e($r['exact_name'])?>, <?=e($r['event_name'])?></strong><div><?=e(ucfirst($r['division']))?>, <?=e(ucfirst($r['dance_role']))?>, existing <?=e((string)(float)$r['existing_event_points'])?>, requested <strong>+<?=e((string)(float)$r['additional_points'])?></strong></div><div class="small text-muted mb-2"><?=e($r['reason'])?>, by <?=e($r['requester_name'])?> at <?=e($r['requested_at'])?></div><input class="form-control form-control-sm mb-2" name="review_reason" placeholder="Review note, optional"><button class="btn btn-sm btn-success" name="action" value="approve">Approve and Append</button> <button class="btn btn-sm btn-outline-danger" name="action" value="reject">Reject</button></form><?php endforeach;?></div></div><?php endif;?>
<div class="card border-warning shadow-sm mb-4"><div class="card-header bg-warning-subtle"><strong>Event Duplicate Finder</strong></div><div class="card-body"><p class="text-muted">Multiple point records for the same contestant, event, division and role. Exact repeated values are probable duplicates; different values require review.</p><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Contestant</th><th>Event</th><th>Division, Role</th><th>Records</th><th>Total</th><th>Finding</th><th></th></tr></thead><tbody><?php if(!$duplicates):?><tr><td colspan="7" class="text-muted">No event duplicates found.</td></tr><?php endif;?><?php foreach($duplicates as $d):?><tr><td><?=e($d['exact_name'])?><br><code><?=e($d['bdc_id'])?></code></td><td><?=e($d['event_name'])?><br><small><?=e($d['event_date'])?></small></td><td><?=e(ucfirst($d['division']).', '.ucfirst($d['dance_role']))?></td><td><?=$d['row_count']?></td><td><?=e((string)(float)$d['total_points'])?></td><td><span class="badge <?=$d['distinct_values']==1?'text-bg-danger':'text-bg-warning'?>"><?=$d['distinct_values']==1?'Probable duplicate':'Review required'?></span></td><td><a class="btn btn-sm btn-outline-dark" href="<?=e(url('admin/competitors/edit.php?id='.(int)$d['competitor_id']))?>">Review rows</a></td></tr><?php endforeach;?></tbody></table></div></div></div>
<?php if($history):?><div class="card shadow-sm border-0"><div class="card-header"><strong>Adjustment Audit History</strong></div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Contestant</th><th>Event</th><th>Points</th><th>Status</th><th>Requested by</th><th>Reviewed by</th></tr></thead><tbody><?php foreach($history as $r):?><tr><td><?=e($r['exact_name'])?></td><td><?=e($r['event_name'])?></td><td>+<?=e((string)(float)$r['additional_points'])?></td><td><?=e(ucfirst($r['status']))?></td><td><?=e($r['requester_name'])?></td><td><?=e($r['reviewer_name']?:'—')?></td></tr><?php endforeach;?></tbody></table></div></div><?php endif;?>
</main></body></html>
