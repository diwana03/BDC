<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth; use App\Core\Csrf; use App\Core\Database; use App\Services\SchemaUpdater;
Auth::requireAdmin();
if (!Auth::can('transactions.edit') && !Auth::can('competitors.edit')) { http_response_code(403); exit('You do not have permission to edit competition entries.'); }
$pdo=Database::connection(); SchemaUpdater::run($pdo);
$id=(int)($_GET['id']??$_POST['id']??0);
$competitorId=(int)($_GET['competitor_id']??$_POST['competitor_id']??0);
$error='';
if ($id) {
 $s=$pdo->prepare('SELECT * FROM bdc_point_transactions WHERE id=:id'); $s->execute(['id'=>$id]); $entry=$s->fetch();
 if(!$entry){http_response_code(404);exit('Competition entry not found.');}
 $competitorId=(int)$entry['competitor_id'];
} else {
 $entry=['id'=>0,'competitor_id'=>$competitorId,'event_id'=>'','division'=>'unknown','dance_role'=>'unknown','placement'=>'','points'=>'0','notes'=>''];
}
$cs=$pdo->prepare('SELECT id,bdc_id,exact_name FROM bdc_competitors WHERE id=:id');$cs->execute(['id'=>$competitorId]);$competitor=$cs->fetch();
if(!$competitor){http_response_code(404);exit('Competitor not found.');}
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!Csrf::verify($_POST['_csrf']??null))$error='Invalid security token.';
 elseif(isset($_POST['delete_entry']) && $id){
  $old=$entry;
  $pdo->beginTransaction();
  try{
   $pdo->prepare('DELETE FROM bdc_participant_results WHERE point_transaction_id=:id')->execute(['id'=>$id]);
   $pdo->prepare('DELETE FROM bdc_point_transactions WHERE id=:id AND competitor_id=:cid')->execute(['id'=>$id,'cid'=>$competitorId]);
   Auth::audit((int)Auth::user()['id'],'point_transaction_deleted',['before'=>$old],'point_transaction',$id);
   $pdo->commit(); header('Location: '.url('admin/competitors/edit.php?id='.$competitorId.'&entry=deleted')); exit;
  }catch(Throwable $e){$pdo->rollBack();$error='Could not delete this competition entry.';}
 } else {
  $eventId=(int)($_POST['event_id']??0); $division=(string)($_POST['division']??'unknown'); $role=(string)($_POST['dance_role']??'unknown');
  $placement=trim((string)($_POST['placement']??'')); $points=(float)($_POST['points']??0); $notes=trim((string)($_POST['notes']??''));
  if($eventId<1)$error='Please select an event.';
  elseif(!in_array($division,['novice','intermediate','advanced','all_star','unknown'],true))$error='Invalid division.';
  elseif(!in_array($role,['leader','follower','both','unknown'],true))$error='Invalid role.';
  else{
   $old=$entry; $uid=(int)Auth::user()['id']; $pdo->beginTransaction();
   try{
    if($id){
     $pdo->prepare("UPDATE bdc_point_transactions SET event_id=:event,division=:division,dance_role=:role,placement=:placement,points=:points,notes=:notes,source_type='correction' WHERE id=:id AND competitor_id=:cid")
      ->execute(['event'=>$eventId,'division'=>$division,'role'=>$role,'placement'=>$placement?:null,'points'=>$points,'notes'=>$notes?:null,'id'=>$id,'cid'=>$competitorId]);
    }else{
     $pdo->prepare("INSERT INTO bdc_point_transactions(competitor_id,event_id,division,dance_role,points,placement,notes,source_type,created_by) VALUES(:cid,:event,:division,:role,:points,:placement,:notes,'manual',:uid)")
      ->execute(['cid'=>$competitorId,'event'=>$eventId,'division'=>$division,'role'=>$role,'points'=>$points,'placement'=>$placement?:null,'notes'=>$notes?:null,'uid'=>$uid]);
     $id=(int)$pdo->lastInsertId();
    }
    $status=in_array(strtolower($placement),['1','1st','first'],true)?'winner':($placement!==''?'placed':'participant');
    $pdo->prepare("INSERT INTO bdc_participant_results(event_id,competitor_id,division,dance_role,placement,finalist_status,points_awarded,source,point_transaction_id) VALUES(:event,:cid,:division,:role,:placement,:status,:points,'manual',:tx) ON DUPLICATE KEY UPDATE event_id=VALUES(event_id),competitor_id=VALUES(competitor_id),division=VALUES(division),dance_role=VALUES(dance_role),placement=VALUES(placement),finalist_status=VALUES(finalist_status),points_awarded=VALUES(points_awarded),source='manual'")
     ->execute(['event'=>$eventId,'cid'=>$competitorId,'division'=>$division,'role'=>$role,'placement'=>$placement?:null,'status'=>$status,'points'=>$points,'tx'=>$id]);
    Auth::audit($uid,$old['id']?'point_transaction_updated':'point_transaction_created',['before'=>$old,'after'=>$_POST],'point_transaction',$id);
    $pdo->commit(); header('Location: '.url('admin/competitors/edit.php?id='.$competitorId.'&entry=saved')); exit;
   }catch(Throwable $e){$pdo->rollBack();$error='Could not save this competition entry.';}
  }
 }
}
$events=$pdo->query('SELECT id,name,event_date FROM bdc_events ORDER BY event_date DESC,name')->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=$id?'Edit':'Add'?> Competition Entry</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><nav class="navbar navbar-dark bg-dark"><div class="container"><a class="navbar-brand" href="<?=e(url('admin/competitors/edit.php?id='.$competitorId))?>">← <?=e($competitor['exact_name'])?></a></div></nav><main class="container py-4" style="max-width:850px"><h1 class="h3"><?=$id?'Edit':'Add'?> competition entry</h1><p class="text-muted">This changes only this historical entry, not the competitor’s current profile division.</p><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><form method="post" class="card border-0 shadow-sm"><div class="card-body p-4"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="competitor_id" value="<?=$competitorId?>"><div class="row g-3"><div class="col-12"><label class="form-label">Event</label><select class="form-select" name="event_id" required><option value="">Select event</option><?php foreach($events as $ev):?><option value="<?=$ev['id']?>" <?=(int)$entry['event_id']===(int)$ev['id']?'selected':''?>><?=e($ev['name'].' · '.($ev['event_date']?:'No date'))?></option><?php endforeach;?></select></div><div class="col-md-6"><label class="form-label">Division at this event</label><select class="form-select" name="division"><?php foreach(['unknown','novice','intermediate','advanced','all_star'] as $v):?><option value="<?=$v?>" <?=$entry['division']===$v?'selected':''?>><?=e(ucwords(str_replace('_',' ',$v)))?></option><?php endforeach;?></select></div><div class="col-md-6"><label class="form-label">Role at this event</label><select class="form-select" name="dance_role"><?php foreach(['unknown','leader','follower','both'] as $v):?><option value="<?=$v?>" <?=$entry['dance_role']===$v?'selected':''?>><?=e(ucfirst($v))?></option><?php endforeach;?></select></div><div class="col-md-6"><label class="form-label">Placement</label><input class="form-control" name="placement" value="<?=e((string)$entry['placement'])?>" placeholder="Example: 1st, 2nd, Finalist"></div><div class="col-md-6"><label class="form-label">Points</label><input class="form-control" type="number" step="0.01" name="points" value="<?=e((string)$entry['points'])?>" required></div><div class="col-12"><label class="form-label">Internal notes</label><textarea class="form-control" name="notes" rows="3"><?=e((string)$entry['notes'])?></textarea></div></div></div><div class="card-footer bg-white d-flex justify-content-between"><div><button class="btn btn-dark">Save entry</button> <a class="btn btn-outline-secondary" href="<?=e(url('admin/competitors/edit.php?id='.$competitorId))?>">Cancel</a></div><?php if($id):?><button class="btn btn-outline-danger" type="submit" name="delete_entry" value="1" onclick="return confirm('Delete this historical entry? This cannot be undone.')">Delete entry</button><?php endif;?></div></form></main></body></html>
