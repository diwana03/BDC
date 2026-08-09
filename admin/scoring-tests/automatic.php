<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\TestAutomaticJudgeService;
use App\Services\TestCompetitorGeneratorService;

Auth::requireAdmin();
$pdo=Database::connection();
$userId=(int)(Auth::user()['id']??0);
$roundId=(int)($_GET['round_id']??$_POST['round_id']??0);
$error='';$notice='';$freshLinks=[];
TestAutomaticJudgeService::ensureSchema($pdo);

function testAutoRound(PDO $pdo,int $id):?array{
    if($id<1)return null;
    $s=$pdo->prepare('SELECT r.*,e.name event_name FROM bdc_test_scoring_rounds r JOIN bdc_test_events e ON e.id=r.event_id WHERE r.id=:id');
    $s->execute(['id'=>$id]);return $s->fetch()?:null;
}

try{
 if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  if(!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Invalid security token.');
  $action=(string)($_POST['action']??'');
  if($action==='create_quick_round'){
    $division=(string)($_POST['division']??'novice');if(!in_array($division,['novice','intermediate','advanced'],true))$division='novice';
    $name='AUTOMATIC TEST '.date('Y-m-d H-i-s');$slug='automatic-test-'.date('YmdHis').'-'.random_int(100,999);
    $pdo->prepare("INSERT INTO bdc_test_events(name,normalised_name,slug,event_date,status,points_tier) VALUES(:n,:nn,:s,CURDATE(),'draft','2')")->execute(['n'=>$name,'nn'=>strtolower($name),'s'=>$slug]);
    $eventId=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO bdc_test_scoring_rounds(event_id,round_type,division,yes_count,callback_count,yes_weight,alt1_weight,alt2_weight,alt3_weight,created_by) VALUES(:e,'heats',:d,10,10,10,4.5,4.3,4.2,:u)")->execute(['e'=>$eventId,'d'=>$division,'u'=>$userId?:null]);
    $roundId=(int)$pdo->lastInsertId();$notice='Automatic test round created.';
  }elseif($action==='generate_competitors'){
    TestCompetitorGeneratorService::generate($pdo,$roundId,(int)($_POST['leaders']??10),(int)($_POST['followers']??10),$userId);$notice='Test competitors generated.';
  }elseif($action==='generate_judges'){
    $count=max(3,min(101,(int)($_POST['judge_count']??5)));$pdo->beginTransaction();
    try{
      TestAutomaticJudgeService::clearJudgeSessions($pdo,$roundId);
      $pdo->prepare('DELETE FROM bdc_test_scoring_marks WHERE round_id=:r')->execute(['r'=>$roundId]);
      $pdo->prepare('DELETE FROM bdc_test_scoring_judges WHERE round_id=:r')->execute(['r'=>$roundId]);
      $ins=$pdo->prepare("INSERT INTO bdc_test_scoring_judges(round_id,judge_name,judge_order,is_chief,scoring_scope) VALUES(:r,:n,:o,:c,'all')");
      $chief=random_int(1,$count);$chiefId=0;
      for($i=1;$i<=$count;$i++){$ins->execute(['r'=>$roundId,'n'=>'Test Judge '.$i,'o'=>$i,'c'=>$i===$chief?1:0]);if($i===$chief)$chiefId=(int)$pdo->lastInsertId();}
      $pdo->prepare('UPDATE bdc_test_scoring_rounds SET chief_judge_id=:c WHERE id=:r')->execute(['c'=>$chiefId,'r'=>$roundId]);$pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    TestAutomaticJudgeService::syncRound($pdo,$roundId);$notice='Judges generated. Create each secure browser link below.';
  }elseif($action==='delete_judge'){
    $judgeId=(int)($_POST['judge_id']??0);$pdo->beginTransaction();try{
      $pdo->prepare('DELETE FROM bdc_test_scoring_marks WHERE round_id=:r AND judge_id=:j')->execute(['r'=>$roundId,'j'=>$judgeId]);
      $pdo->prepare('DELETE FROM bdc_test_scoring_judge_sessions WHERE round_id=:r AND judge_id=:j')->execute(['r'=>$roundId,'j'=>$judgeId]);
      $pdo->prepare('DELETE FROM bdc_test_scoring_judges WHERE round_id=:r AND id=:j')->execute(['r'=>$roundId,'j'=>$judgeId]);$pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}$notice='Judge deleted.';
  }elseif($action==='delete_all_judges'){
    $pdo->beginTransaction();try{TestAutomaticJudgeService::clearJudgeSessions($pdo,$roundId);$pdo->prepare('DELETE FROM bdc_test_scoring_marks WHERE round_id=:r')->execute(['r'=>$roundId]);$pdo->prepare('DELETE FROM bdc_test_scoring_judges WHERE round_id=:r')->execute(['r'=>$roundId]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}$notice='All judges cleared.';
  }elseif($action==='delete_entry'){
    $entryId=(int)($_POST['entry_id']??0);$pdo->prepare('DELETE FROM bdc_test_scoring_marks WHERE round_id=:r AND entry_id=:e')->execute(['r'=>$roundId,'e'=>$entryId]);$pdo->prepare('DELETE FROM bdc_test_scoring_entries WHERE round_id=:r AND id=:e')->execute(['r'=>$roundId,'e'=>$entryId]);$notice='Competitor removed.';
  }elseif($action==='delete_all_entries'){
    $pdo->prepare('DELETE FROM bdc_test_scoring_marks WHERE round_id=:r')->execute(['r'=>$roundId]);$pdo->prepare('DELETE FROM bdc_test_scoring_entries WHERE round_id=:r')->execute(['r'=>$roundId]);$notice='All competitors cleared.';
  }elseif($action==='regenerate_link'){
    $judgeId=(int)($_POST['judge_id']??0);$token=TestAutomaticJudgeService::regenerate($pdo,$roundId,$judgeId);$freshLinks[$judgeId]=TestAutomaticJudgeService::publicUrl($token);$notice='New secure judge link generated. Copy it now.';
  }elseif($action==='random_browser_scores'){
    $judges=$pdo->prepare('SELECT * FROM bdc_test_scoring_judges WHERE round_id=:r');$judges->execute(['r'=>$roundId]);$judges=$judges->fetchAll();
    $entries=$pdo->prepare("SELECT * FROM bdc_test_scoring_entries WHERE round_id=:r AND entry_status='active'");$entries->execute(['r'=>$roundId]);$entries=$entries->fetchAll();
    if(count($judges)<3||!$entries)throw new RuntimeException('Generate competitors and at least 3 judges first.');
    $up=$pdo->prepare("INSERT INTO bdc_test_scoring_marks(round_id,entry_id,judge_id,mark_type,alt_rank,weighted_score,updated_by) VALUES(:r,:e,:j,:t,:a,:w,:u) ON DUPLICATE KEY UPDATE mark_type=VALUES(mark_type),alt_rank=VALUES(alt_rank),weighted_score=VALUES(weighted_score),updated_by=VALUES(updated_by),updated_at=NOW()");
    foreach($judges as $j){foreach($entries as $e){$scope=(string)($j['scoring_scope']??'all');if($scope!=='all'&&$scope!==$e['dance_role'])continue;$roll=random_int(1,100);if($roll<=45){$t='yes';$a=null;$w=10;}elseif($roll<=65){$t='alt';$a=1;$w=4.5;}elseif($roll<=82){$t='alt';$a=2;$w=4.3;}else{$t='alt';$a=3;$w=4.2;}$up->execute(['r'=>$roundId,'e'=>$e['id'],'j'=>$j['id'],'t'=>$t,'a'=>$a,'w'=>$w,'u'=>$userId]);}
      $session=$pdo->prepare('SELECT id FROM bdc_test_scoring_judge_sessions WHERE round_id=:r AND judge_id=:j');$session->execute(['r'=>$roundId,'j'=>$j['id']]);$sid=(int)$session->fetchColumn();if(!$sid){TestAutomaticJudgeService::regenerate($pdo,$roundId,(int)$j['id']);$session->execute(['r'=>$roundId,'j'=>$j['id']]);$sid=(int)$session->fetchColumn();}if($sid)TestAutomaticJudgeService::submit($pdo,$sid);
    }$notice='Random Automatic test completed through judge sessions; all judges are submitted/locked.';
  }elseif($action==='clear_round'){
    $pdo->beginTransaction();try{TestAutomaticJudgeService::clearJudgeSessions($pdo,$roundId);foreach(['bdc_test_scoring_results','bdc_test_scoring_marks','bdc_test_scoring_judges','bdc_test_scoring_entries'] as $table)$pdo->prepare("DELETE FROM {$table} WHERE round_id=:r")->execute(['r'=>$roundId]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}$notice='Entire test round cleared.';
  }
 }
}catch(Throwable $e){$error=$e->getMessage();}

$round=testAutoRound($pdo,$roundId);
$rounds=$pdo->query("SELECT r.id,r.division,r.round_type,e.name event_name FROM bdc_test_scoring_rounds r JOIN bdc_test_events e ON e.id=r.event_id ORDER BY r.id DESC LIMIT 20")->fetchAll();
$judges=[];$entries=[];$progress=[];
if($round){$q=$pdo->prepare('SELECT * FROM bdc_test_scoring_judges WHERE round_id=:r ORDER BY judge_order');$q->execute(['r'=>$roundId]);$judges=$q->fetchAll();$q=$pdo->prepare("SELECT * FROM bdc_test_scoring_entries WHERE round_id=:r AND entry_status='active' ORDER BY dance_role,bib_number");$q->execute(['r'=>$roundId]);$entries=$q->fetchAll();$progress=TestAutomaticJudgeService::progress($pdo,$roundId);}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Automatic Scoring Test</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:#f4f6f9}.card{border-radius:14px}.mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.82rem}.status-submitted{color:#198754}.status-scoring{color:#0d6efd}.status-not_started{color:#6c757d}</style></head><body><nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><span class="navbar-brand">Automatic Scoring Test</span><div class="d-flex gap-2"><a class="btn btn-outline-light btn-sm" href="select-mode.php">Scoring Test Modes</a><a class="btn btn-warning btn-sm" href="../">Dashboard</a></div></div></nav><main class="container-fluid py-4" style="max-width:1600px">
<div class="alert alert-info"><strong>TEST ONLY.</strong> Judge browser input uses disposable <code>bdc_test_*</code> scoring data. Calculation remains the shared BDC scoring engine.</div>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><?php if($notice):?><div class="alert alert-success"><?=e($notice)?></div><?php endif;?>
<div class="row g-3"><div class="col-lg-4"><div class="card"><div class="card-body"><h2 class="h5">Quick Automatic Test Round</h2><form method="post"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="action" value="create_quick_round"><select class="form-select mb-2" name="division"><option value="novice">Novice</option><option value="intermediate">Intermediate</option><option value="advanced">Advanced</option></select><button class="btn btn-primary w-100">Create Automatic Test Round</button></form></div></div></div><div class="col-lg-8"><div class="card"><div class="card-body"><h2 class="h5">Open Test Round</h2><div class="d-flex gap-2 flex-wrap"><?php foreach($rounds as $r):?><a class="btn btn-outline-dark btn-sm" href="?round_id=<?=(int)$r['id']?>">#<?=(int)$r['id']?> <?=e($r['event_name'])?> · <?=e($r['division'])?></a><?php endforeach;?></div></div></div></div></div>
<?php if($round):?><hr><div class="d-flex justify-content-between align-items-center"><div><h1 class="h3 mb-1"><?=e($round['event_name'])?></h1><div class="text-muted"><?=e($round['division'])?> · <?=e($round['round_type'])?></div></div><form method="post" onsubmit="return confirm('Clear judges, judge sessions, competitors, marks and results for this TEST round?')"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="action" value="clear_round"><button class="btn btn-danger">Clear Entire Test Round</button></form></div>
<div class="row g-3 mt-1"><div class="col-lg-6"><div class="card h-100"><div class="card-body"><div class="d-flex justify-content-between"><h2 class="h5">Competitors</h2><form method="post" onsubmit="return confirm('Delete all test competitors?')"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="action" value="delete_all_entries"><button class="btn btn-outline-danger btn-sm">Delete All</button></form></div><form method="post" class="row g-2 mb-3"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="action" value="generate_competitors"><div class="col"><input class="form-control" type="number" name="leaders" value="10" min="0" max="500"><small>Leaders</small></div><div class="col"><input class="form-control" type="number" name="followers" value="10" min="0" max="500"><small>Followers</small></div><div class="col-auto"><button class="btn btn-primary">Generate</button></div></form><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Bib</th><th>Role</th><th>Name</th><th></th></tr></thead><tbody><?php foreach($entries as $e):?><tr><td><?=(int)$e['bib_number']?></td><td><?=e($e['dance_role'])?></td><td><?=e($e['display_name'])?></td><td><form method="post"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="action" value="delete_entry"><input type="hidden" name="entry_id" value="<?=(int)$e['id']?>"><button class="btn btn-outline-danger btn-sm">Delete</button></form></td></tr><?php endforeach;?></tbody></table></div></div></div></div>
<div class="col-lg-6"><div class="card h-100"><div class="card-body"><div class="d-flex justify-content-between"><h2 class="h5">Judges & Secure Browser Links</h2><form method="post" onsubmit="return confirm('Delete all test judges and their marks?')"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="action" value="delete_all_judges"><button class="btn btn-outline-danger btn-sm">Delete All</button></form></div><form method="post" class="d-flex gap-2 mb-3"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="action" value="generate_judges"><input class="form-control" style="max-width:120px" type="number" name="judge_count" value="5" min="3" max="101"><button class="btn btn-primary">Generate Judges</button></form><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Judge</th><th>Progress</th><th>Secure URL</th><th></th></tr></thead><tbody><?php foreach($progress as $j):$jid=(int)$j['judge_id'];?><tr><td><?=e($j['judge_name'])?><?=((int)$j['is_chief']===1?' ⭐':'')?><div class="small status-<?=e($j['session_status'])?>"><?=e(str_replace('_',' ',$j['session_status']))?></div></td><td><?=$j['done']?> / <?=$j['total']?> (<?=$j['percent']?>%)</td><td><?php if(isset($freshLinks[$jid])):?><div class="mono text-break"><?=e($freshLinks[$jid])?></div><?php else:?><form method="post"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="judge_id" value="<?=$jid?>"><input type="hidden" name="action" value="regenerate_link"><button class="btn btn-outline-primary btn-sm">Generate / Regenerate Link</button></form><?php endif;?></td><td><form method="post"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="judge_id" value="<?=$jid?>"><input type="hidden" name="action" value="delete_judge"><button class="btn btn-outline-danger btn-sm">Delete</button></form></td></tr><?php endforeach;?></tbody></table></div><form method="post" class="mt-3"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="action" value="random_browser_scores"><button class="btn btn-warning">Simulate Random Judge Browser Submissions</button></form></div></div></div></div>
<script>setTimeout(function(){if(document.visibilityState==='visible'&&!document.querySelector('.mono'))location.reload();},8000);</script><?php endif;?></main></body></html>
