<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\SchemaUpdater;

Auth::requirePermission('registrations.manage');
$pdo=Database::connection();
SchemaUpdater::run($pdo);
$error='';$notice='';$report=null;

$maps=[
 '1'=>['5'=>'1st','4'=>'2nd','3'=>'3rd','2'=>'4th','1'=>'5th'],
 '2'=>['10'=>'1st','8'=>'2nd','6'=>'3rd','4'=>'4th','2'=>'5th','1'=>'6th–10th'],
 '3'=>['15'=>'1st','12'=>'2nd','10'=>'3rd','8'=>'4th','6'=>'5th','2'=>'Finalist'],
];
function pointsKey($value): string {$n=(float)$value;return abs($n-round($n))<0.00001?(string)(int)round($n):rtrim(rtrim(number_format($n,2,'.',''),'0'),'.');}
function resultStatus(string $p): string {if($p==='1st')return'winner';if(in_array($p,['2nd','3rd','4th','5th'],true))return'placed';return'finalist';}
function labelize(string $v): string {return ucwords(str_replace('_',' ',$v));}

$eventId=(int)($_GET['event_id']??$_POST['event_id']??0);
try{
 if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!Csrf::verify($_POST['_csrf']??null)) throw new RuntimeException('Invalid security token.');
  if($eventId<1) throw new RuntimeException('Select an event.');
  $overwrite=isset($_POST['overwrite_existing']);
  $tiers=$_POST['tier']??[];
  if(!is_array($tiers)) throw new RuntimeException('Invalid tier configuration.');

  $comboStmt=$pdo->prepare("SELECT DISTINCT division,dance_role FROM bdc_point_transactions WHERE event_id=:event AND division IN ('novice','intermediate','advanced','all_star') AND dance_role IN ('leader','follower') ORDER BY FIELD(division,'novice','intermediate','advanced','all_star'),FIELD(dance_role,'leader','follower')");
  $comboStmt->execute(['event'=>$eventId]);$combos=$comboStmt->fetchAll();
  $valid=[];
  foreach($combos as $combo){$key=$combo['division'].'|'.$combo['dance_role'];$tier=(string)($tiers[$key]??'');if(isset($maps[$tier]))$valid[$key]=$tier;}
  if(!$valid) throw new RuntimeException('Select at least one valid tier.');

  $pdo->beginTransaction();
  $save=$pdo->prepare("INSERT INTO bdc_event_points_tiers(event_id,division,dance_role,points_tier) VALUES(:event,:division,:role,:tier) ON DUPLICATE KEY UPDATE points_tier=VALUES(points_tier),updated_at=CURRENT_TIMESTAMP");
  $rowsStmt=$pdo->prepare("SELECT p.*,c.bdc_id,c.exact_name FROM bdc_point_transactions p LEFT JOIN bdc_competitors c ON c.id=p.competitor_id WHERE p.event_id=:event AND p.division=:division AND p.dance_role=:role".(!$overwrite?" AND (p.placement IS NULL OR TRIM(p.placement)='')":"")." ORDER BY p.id");
  $updateTx=$pdo->prepare('UPDATE bdc_point_transactions SET placement=:placement WHERE id=:id');
  $upsertHistory=$pdo->prepare("INSERT INTO bdc_participant_results(event_id,competitor_id,division,dance_role,placement,finalist_status,points_awarded,source,point_transaction_id) VALUES(:event,:competitor,:division,:role,:placement,:status,:points,'manual',:tx) ON DUPLICATE KEY UPDATE event_id=VALUES(event_id),competitor_id=VALUES(competitor_id),division=VALUES(division),dance_role=VALUES(dance_role),placement=VALUES(placement),finalist_status=VALUES(finalist_status),points_awarded=VALUES(points_awarded),source='manual'");
  $updated=0;$unmatched=[];$breakdown=[];
  foreach($valid as $key=>$tier){[$division,$role]=explode('|',$key,2);$save->execute(['event'=>$eventId,'division'=>$division,'role'=>$role,'tier'=>$tier]);$rowsStmt->execute(['event'=>$eventId,'division'=>$division,'role'=>$role]);$count=0;
   foreach($rowsStmt->fetchAll() as $row){$pointKey=pointsKey($row['points']);if(!isset($maps[$tier][$pointKey])){$unmatched[]=['id'=>$row['id'],'bdc_id'=>$row['bdc_id'],'name'=>$row['exact_name'],'division'=>$division,'role'=>$role,'tier'=>$tier,'points'=>$row['points']];continue;}$placement=$maps[$tier][$pointKey];$updateTx->execute(['placement'=>$placement,'id'=>$row['id']]);$upsertHistory->execute(['event'=>$eventId,'competitor'=>$row['competitor_id'],'division'=>$division,'role'=>$role,'placement'=>$placement,'status'=>resultStatus($placement),'points'=>$row['points'],'tx'=>$row['id']]);$updated++;$count++;}
   $breakdown[]=['division'=>$division,'role'=>$role,'tier'=>$tier,'updated'=>$count];
  }
  $pdo->commit();$report=['updated'=>$updated,'unmatched'=>$unmatched,'breakdown'=>$breakdown];$notice=$updated.' placement'.($updated===1?'':'s').' recalculated using division and role-specific tiers.';
 }
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$error=$e->getMessage();}

$events=$pdo->query("SELECT e.id,e.name,e.event_date,COUNT(p.id) transaction_count,SUM(CASE WHEN p.placement IS NULL OR TRIM(p.placement)='' THEN 1 ELSE 0 END) missing_count FROM bdc_events e LEFT JOIN bdc_point_transactions p ON p.event_id=e.id GROUP BY e.id,e.name,e.event_date ORDER BY e.event_date DESC,e.id DESC")->fetchAll();
$combos=[];$saved=[];
if($eventId>0){$s=$pdo->prepare("SELECT division,dance_role,COUNT(*) transaction_count,SUM(CASE WHEN placement IS NULL OR TRIM(placement)='' THEN 1 ELSE 0 END) missing_count FROM bdc_point_transactions WHERE event_id=:event AND division IN ('novice','intermediate','advanced','all_star') AND dance_role IN ('leader','follower') GROUP BY division,dance_role ORDER BY FIELD(division,'novice','intermediate','advanced','all_star'),FIELD(dance_role,'leader','follower')");$s->execute(['event'=>$eventId]);$combos=$s->fetchAll();$t=$pdo->prepare('SELECT division,dance_role,points_tier FROM bdc_event_points_tiers WHERE event_id=:event');$t->execute(['event'=>$eventId]);foreach($t->fetchAll() as $r)$saved[$r['division'].'|'.$r['dance_role']]=$r['points_tier'];}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Recalculate Placements | BDC Admin</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="../">BDC Admin</a><a class="btn btn-outline-light btn-sm" href="../competitors/">Competitors</a></div></nav><main class="container py-4" style="max-width:1100px"><div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h3 mb-1">Recalculate Competition Placements</h1><div class="text-muted">Configure a separate BDC tier for each division and role.</div></div><a class="btn btn-outline-secondary" href="../">Dashboard</a></div>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><?php if($notice):?><div class="alert alert-success"><?=e($notice)?></div><?php endif;?>
<div class="alert alert-warning"><strong>Safe default:</strong> existing placements are not changed. Select “overwrite” only after confirming every division and role tier.</div>
<div class="card border-0 shadow-sm mb-4"><div class="card-body"><form method="get" class="row g-3 align-items-end"><div class="col-md-10"><label class="form-label">Event</label><select class="form-select" name="event_id" required><option value="">Select event</option><?php foreach($events as $event):?><option value="<?=$event['id']?>" <?=$eventId===(int)$event['id']?'selected':''?>><?=e((string)$event['event_date'])?> — <?=e($event['name'])?> (<?=$event['missing_count']?> missing of <?=$event['transaction_count']?>)</option><?php endforeach;?></select></div><div class="col-md-2"><button class="btn btn-outline-dark w-100">Load event</button></div></form></div></div>
<?php if($eventId>0):?><div class="card border-0 shadow-sm"><div class="card-body"><form method="post"><?=Csrf::field()?><input type="hidden" name="event_id" value="<?=$eventId?>"><h2 class="h5 mb-3">Division and role tiers</h2><?php if(!$combos):?><div class="alert alert-info mb-0">No Leader or Follower point transactions were found for this event.</div><?php else:?><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Division</th><th>Role</th><th>Transactions</th><th>Missing</th><th style="min-width:270px">BDC points tier</th></tr></thead><tbody><?php foreach($combos as $combo):$key=$combo['division'].'|'.$combo['dance_role'];$selected=$saved[$key]??'';?><tr><td><?=e(labelize($combo['division']))?></td><td><?=e(labelize($combo['dance_role']))?></td><td><?=$combo['transaction_count']?></td><td><?=$combo['missing_count']?></td><td><select class="form-select" name="tier[<?=e($key)?>]"><option value="">Do not recalculate</option><option value="1" <?=$selected==='1'?'selected':''?>>Tier 1, 5–15 competitors</option><option value="2" <?=$selected==='2'?'selected':''?>>Tier 2, 16–30 competitors</option><option value="3" <?=$selected==='3'?'selected':''?>>Tier 3, 30+ competitors</option></select></td></tr><?php endforeach;?></tbody></table></div><div class="d-flex flex-wrap justify-content-between gap-3 align-items-center"><label class="form-check"><input class="form-check-input" type="checkbox" name="overwrite_existing"><span class="form-check-label">Overwrite existing placements for configured groups</span></label><button class="btn btn-dark px-4">Save tiers and recalculate</button></div><?php endif;?></form></div></div><?php endif;?>
<div class="card border-0 shadow-sm mt-4"><div class="card-body"><h2 class="h5">BDC placement matrix</h2><div class="table-responsive"><table class="table table-bordered mb-0"><thead><tr><th>Tier</th><th>1st</th><th>2nd</th><th>3rd</th><th>4th</th><th>5th</th><th>Additional</th></tr></thead><tbody><tr><td>1</td><td>5</td><td>4</td><td>3</td><td>2</td><td>1</td><td>—</td></tr><tr><td>2</td><td>10</td><td>8</td><td>6</td><td>4</td><td>2</td><td>1 point = 6th–10th</td></tr><tr><td>3</td><td>15</td><td>12</td><td>10</td><td>8</td><td>6</td><td>2 points = Finalist</td></tr></tbody></table></div></div></div>
<?php if($report):?><div class="card border-0 shadow-sm mt-4"><div class="card-body"><h2 class="h5">Recalculation summary</h2><div class="table-responsive"><table class="table"><thead><tr><th>Division</th><th>Role</th><th>Tier</th><th>Updated</th></tr></thead><tbody><?php foreach($report['breakdown'] as $b):?><tr><td><?=e(labelize($b['division']))?></td><td><?=e(labelize($b['role']))?></td><td>Tier <?=e($b['tier'])?></td><td><?=$b['updated']?></td></tr><?php endforeach;?></tbody></table></div></div></div><?php endif;?>
<?php if($report && $report['unmatched']):?><div class="card border-warning shadow-sm mt-4"><div class="card-body"><h2 class="h5">Not changed</h2><p class="text-muted">These point values do not exist in the selected group’s tier matrix. Review them manually.</p><div class="table-responsive"><table class="table"><thead><tr><th>Transaction</th><th>Competitor</th><th>Division</th><th>Role</th><th>Tier</th><th>Points</th></tr></thead><tbody><?php foreach($report['unmatched'] as $u):?><tr><td>#<?=$u['id']?></td><td><?=e(trim(($u['bdc_id']??'').' '.($u['name']??'')))?></td><td><?=e(labelize($u['division']))?></td><td><?=e(labelize($u['role']))?></td><td>Tier <?=e($u['tier'])?></td><td><?=e((string)$u['points'])?></td></tr><?php endforeach;?></tbody></table></div></div></div><?php endif;?>
</main></body></html>
