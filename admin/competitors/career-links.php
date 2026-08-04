<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\SchemaUpdater;

Auth::requirePermission('competitors.edit');
$pdo=Database::connection();

$error='';$success='';
$sourceId=(int)($_GET['source_id']??$_POST['source_id']??0);
$destinationId=(int)($_GET['destination_id']??$_POST['destination_id']??0);

function competitorRow(PDO $pdo,int $id):?array{
 if($id<=0)return null;
 $s=$pdo->prepare("SELECT c.*,g.display_name career_group_name,COALESCE(SUM(pt.points),0) career_points FROM bdc_competitors c LEFT JOIN bdc_competitor_career_groups g ON g.id=c.career_group_id LEFT JOIN bdc_point_transactions pt ON pt.competitor_id=c.id WHERE c.id=:id GROUP BY c.id,g.display_name");
 $s->execute(['id'=>$id]);$r=$s->fetch();return$r?:null;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
 Csrf::verify((string)($_POST['_csrf']??''));
 $action=(string)($_POST['action']??'');
 try{
  if($action==='move_results'){
   if($sourceId<=0||$destinationId<=0||$sourceId===$destinationId)throw new RuntimeException('Choose two different competitor records.');
   $ids=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['transaction_ids']??[])))));
   if(!$ids)throw new RuntimeException('Select at least one result to move.');
   $marks=implode(',',array_fill(0,count($ids),'?'));
   $pdo->beginTransaction();
   $params=array_merge([$destinationId,$sourceId],$ids);
   $u=$pdo->prepare("UPDATE bdc_point_transactions SET competitor_id=? WHERE competitor_id=? AND id IN ($marks)");
   $u->execute($params);$moved=$u->rowCount();
   $p=array_merge([$destinationId,$sourceId],$ids);
   $u2=$pdo->prepare("UPDATE bdc_participant_results SET competitor_id=? WHERE competitor_id=? AND point_transaction_id IN ($marks)");
   $u2->execute($p);
   Auth::audit((int)Auth::user()['id'],'competitor_results_moved',['source_id'=>$sourceId,'destination_id'=>$destinationId,'transaction_ids'=>$ids,'moved'=>$moved],'competitor',$destinationId);
   $pdo->commit();$success=$moved.' result(s) moved successfully.';
  }elseif($action==='link_career'){
   if($sourceId<=0||$destinationId<=0||$sourceId===$destinationId)throw new RuntimeException('Choose two different competitor records.');
   $a=competitorRow($pdo,$sourceId);$b=competitorRow($pdo,$destinationId);if(!$a||!$b)throw new RuntimeException('Competitor not found.');
   $display=trim((string)($_POST['display_name']??''));if($display==='')$display=$a['exact_name'];
   $pdo->beginTransaction();
   $ga=(int)($a['career_group_id']??0);$gb=(int)($b['career_group_id']??0);
   if($ga>0){$group=$ga;}elseif($gb>0){$group=$gb;}else{
    $i=$pdo->prepare('INSERT INTO bdc_competitor_career_groups(display_name,created_by) VALUES(:n,:u)');$i->execute(['n'=>$display,'u'=>(int)Auth::user()['id']]);$group=(int)$pdo->lastInsertId();
   }
   if($ga>0&&$gb>0&&$ga!==$gb){$pdo->prepare('UPDATE bdc_competitors SET career_group_id=:keep WHERE career_group_id=:old')->execute(['keep'=>$group,'old'=>$gb]);$pdo->prepare('DELETE FROM bdc_competitor_career_groups WHERE id=:id')->execute(['id'=>$gb]);}
   $pdo->prepare('UPDATE bdc_competitors SET career_group_id=:g WHERE id IN (:a,:b)')->execute(['g'=>$group,'a'=>$sourceId,'b'=>$destinationId]);
   $pdo->prepare('UPDATE bdc_competitor_career_groups SET display_name=:n WHERE id=:g')->execute(['n'=>$display,'g'=>$group]);
   Auth::audit((int)Auth::user()['id'],'competitor_career_linked',['group_id'=>$group,'competitor_ids'=>[$sourceId,$destinationId],'display_name'=>$display],'competitor',$sourceId);
   $pdo->commit();$success='Career records linked. Hall of Fame now combines Leader and Follower points.';
  }elseif($action==='unlink_career'){
   $id=(int)($_POST['competitor_id']??0);if($id<=0)throw new RuntimeException('Competitor not selected.');
   $pdo->prepare('UPDATE bdc_competitors SET career_group_id=NULL WHERE id=:id')->execute(['id'=>$id]);
   Auth::audit((int)Auth::user()['id'],'competitor_career_unlinked',['competitor_id'=>$id],'competitor',$id);$success='Career link removed.';
  }
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$error=$e->getMessage();}
}
$source=competitorRow($pdo,$sourceId);$destination=competitorRow($pdo,$destinationId);
$transactions=[];
if($source){$s=$pdo->prepare("SELECT pt.id,pt.event_id,e.name event_name,e.event_date,pt.division,pt.dance_role,pt.placement,pt.points FROM bdc_point_transactions pt LEFT JOIN bdc_events e ON e.id=pt.event_id WHERE pt.competitor_id=:id ORDER BY e.event_date DESC,pt.id DESC");$s->execute(['id'=>$sourceId]);$transactions=$s->fetchAll();}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Move Results & Career Links | BDC Admin</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="<?=e(url('admin/'))?>">BDC Admin</a><div class="d-flex gap-2"><a class="btn btn-outline-light btn-sm" href="merge.php">Merge Competitors</a><a class="btn btn-warning btn-sm" href="https://bachatadancecouncil.com/">🏠 BDC Home</a></div></div></nav>
<main class="container py-4" style="max-width:1200px"><h1 class="h3">Move Results & Career Links</h1><p class="text-muted">Move selected competition results to the correct BDC ID, and link separate Leader and Follower records for combined Hall of Fame career points.</p>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><?php if($success):?><div class="alert alert-success"><?=e($success)?></div><?php endif;?>
<form method="get" class="card border-0 shadow-sm mb-4"><div class="card-body"><div class="row g-3"><div class="col-md-5"><label class="form-label">Source competitor database ID</label><input class="form-control" type="number" name="source_id" value="<?=$sourceId?>" required></div><div class="col-md-5"><label class="form-label">Destination competitor database ID</label><input class="form-control" type="number" name="destination_id" value="<?=$destinationId?>" required></div><div class="col-md-2 d-flex align-items-end"><button class="btn btn-dark w-100">Load</button></div></div><div class="form-text mt-2">Use the numeric database IDs, not the BDC-000000 number. Example: Melissa Follower 206, Melissa Leader 352.</div></div></form>
<?php if($source&&$destination):?><div class="row g-3 mb-4"><div class="col-md-6"><div class="card border-danger h-100"><div class="card-header bg-danger-subtle">Source</div><div class="card-body"><h2 class="h5"><?=e($source['exact_name'])?></h2><code><?=e($source['bdc_id'])?></code><div>Role: <?=e(ucfirst($source['dance_role']))?>, Career points: <?=e((string)(float)$source['career_points'])?></div><div>Career group: <?=e($source['career_group_name']?:'Not linked')?></div></div></div></div><div class="col-md-6"><div class="card border-success h-100"><div class="card-header bg-success-subtle">Destination</div><div class="card-body"><h2 class="h5"><?=e($destination['exact_name'])?></h2><code><?=e($destination['bdc_id'])?></code><div>Role: <?=e(ucfirst($destination['dance_role']))?>, Career points: <?=e((string)(float)$destination['career_points'])?></div><div>Career group: <?=e($destination['career_group_name']?:'Not linked')?></div></div></div></div></div>
<form method="post" class="card border-0 shadow-sm mb-4"><div class="card-header fw-semibold">Move selected results</div><div class="card-body p-0"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="action" value="move_results"><input type="hidden" name="source_id" value="<?=$sourceId?>"><input type="hidden" name="destination_id" value="<?=$destinationId?>"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('.tx').forEach(x=>x.checked=this.checked)"></th><th>Event</th><th>Division</th><th>Role</th><th>Placement</th><th>Points</th></tr></thead><tbody><?php foreach($transactions as $t):?><tr><td><input class="form-check-input tx" type="checkbox" name="transaction_ids[]" value="<?=(int)$t['id']?>"></td><td><?=e($t['event_name']?:'No event')?><?php if($t['event_date']):?><div class="small text-muted"><?=e($t['event_date'])?></div><?php endif;?></td><td><?=e(ucfirst($t['division']))?></td><td><?=e(ucfirst($t['dance_role']))?></td><td><?=e($t['placement']?:'—')?></td><td><?=e((string)(float)$t['points'])?></td></tr><?php endforeach;?></tbody></table></div></div><div class="card-footer"><button class="btn btn-primary" onclick="return confirm('Move the selected results to the destination competitor?')">Move selected results</button></div></form>
<form method="post" class="card border-primary shadow-sm"><div class="card-header bg-primary-subtle fw-semibold">Combine career points</div><div class="card-body"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="action" value="link_career"><input type="hidden" name="source_id" value="<?=$sourceId?>"><input type="hidden" name="destination_id" value="<?=$destinationId?>"><label class="form-label">Hall of Fame display name</label><input class="form-control mb-3" name="display_name" value="<?=e($source['career_group_name']?:$source['exact_name'])?>" required><p class="text-muted">This combines both BDC IDs for Hall of Fame career points. Division leaderboards remain separate by role and BDC ID.</p><button class="btn btn-primary">Link career records</button></div></form><?php endif;?>
</main></body></html>
