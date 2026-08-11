<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';

use App\Core\Csrf;
use App\Core\Database;
use App\Services\TestAutomaticJudgeService;

$pdo=Database::connection();
$token=(string)($_GET['token']??$_POST['token']??'');
$session=TestAutomaticJudgeService::byToken($pdo,$token);
if(!$session){http_response_code(404);exit('Invalid or expired test judge link.');}
$sessionId=(int)$session['id'];$roundId=(int)$session['round_id'];$judgeId=(int)$session['judge_id'];$error='';$notice='';
TestAutomaticJudgeService::markOpened($pdo,$sessionId);

$scope=(string)$session['scoring_scope'];
$entrySql="SELECT * FROM bdc_test_scoring_entries WHERE round_id=:r AND entry_status='active'";
$params=['r'=>$roundId];
if($scope!=='all'){$entrySql.=' AND dance_role=:role';$params['role']=$scope;}
$entrySql.=' ORDER BY dance_role,bib_number';
$q=$pdo->prepare($entrySql);$q->execute($params);$entries=$q->fetchAll();

try{
 if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  if((string)$session['status']==='submitted')throw new RuntimeException('Scores are already submitted and locked.');
  if(!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Invalid security token.');
  $action=(string)($_POST['action']??'save');
  $marks=$_POST['mark']??[];
  $up=$pdo->prepare("INSERT INTO bdc_test_scoring_marks(round_id,entry_id,judge_id,mark_type,alt_rank,weighted_score,updated_by) VALUES(:r,:e,:j,:t,:a,:w,NULL) ON DUPLICATE KEY UPDATE mark_type=VALUES(mark_type),alt_rank=VALUES(alt_rank),weighted_score=VALUES(weighted_score),updated_at=NOW()");
  foreach($entries as $entry){$eid=(int)$entry['id'];$value=(string)($marks[$eid]??'blank');
    $type='blank';$alt=null;$weight=0.0;
    if($value==='yes'){$type='yes';$weight=10.0;}
    elseif(in_array($value,['alt1','alt2','alt3'],true)){$type='alt';$alt=(int)substr($value,-1);$weight=[1=>4.5,2=>4.3,3=>4.2][$alt];}
    $up->execute(['r'=>$roundId,'e'=>$eid,'j'=>$judgeId,'t'=>$type,'a'=>$alt,'w'=>$weight]);
  }
  TestAutomaticJudgeService::markSaved($pdo,$sessionId);
  if($action==='submit'){TestAutomaticJudgeService::submit($pdo,$sessionId);$notice='Scores submitted and locked.';}else{$notice='Scores saved.';}
  $session=TestAutomaticJudgeService::byToken($pdo,$token);
 }
}catch(Throwable $e){$error=$e->getMessage();}

$existing=[];$m=$pdo->prepare('SELECT entry_id,mark_type,alt_rank FROM bdc_test_scoring_marks WHERE round_id=:r AND judge_id=:j');$m->execute(['r'=>$roundId,'j'=>$judgeId]);foreach($m->fetchAll() as $row){$v=(string)$row['mark_type'];if($v==='alt')$v='alt'.(int)$row['alt_rank'];$existing[(int)$row['entry_id']]=$v;}
$locked=(string)$session['status']==='submitted';
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Test Judge Scoring</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:#f4f6f9}.score-row{background:#fff;border:1px solid #dee2e6;border-radius:12px;padding:14px;margin-bottom:10px}.mark-btn input{display:none}.mark-btn label{min-width:58px}.mark-btn input:checked+label{background:#212529;color:#fff}</style></head><body><nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><span class="navbar-brand">BDC Automatic Scoring TEST</span><span class="badge text-bg-warning">TEST ONLY</span></div></nav><main class="container py-4" style="max-width:900px"><h1 class="h3"><?=e($session['event_name'])?></h1><div class="text-muted mb-3"><?=e($session['division'])?> · <?=e($session['judge_name'])?><?=((int)$session['is_chief']===1?' · Chief Judge':'')?></div><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><?php if($notice):?><div class="alert alert-success"><?=e($notice)?></div><?php endif;?><?php if($locked):?><div class="alert alert-success"><strong>Submitted.</strong> These scores are locked.</div><?php endif;?><form method="post"><input type="hidden" name="_csrf" value="<?=e(Csrf::token())?>"><input type="hidden" name="token" value="<?=e($token)?>"><?php foreach($entries as $e):$eid=(int)$e['id'];$selected=$existing[$eid]??'blank';?><div class="score-row"><div class="d-flex justify-content-between align-items-center mb-2"><div><strong>#<?=(int)$e['bib_number']?> <?=e($e['display_name'])?></strong><div class="small text-muted"><?=e(ucfirst($e['dance_role']))?></div></div></div><div class="d-flex gap-2 flex-wrap mark-btn"><?php foreach(['yes'=>'YES','alt1'=>'A1','alt2'=>'A2','alt3'=>'A3','blank'=>'Blank'] as $value=>$label):?><span><input id="m<?=$eid?>-<?=$value?>" type="radio" name="mark[<?=$eid?>]" value="<?=$value?>" <?=($selected===$value?'checked':'')?> <?=($locked?'disabled':'')?>><label class="btn btn-outline-dark" for="m<?=$eid?>-<?=$value?>"><?=$label?></label></span><?php endforeach;?></div></div><?php endforeach;?><?php if(!$locked):?><div class="position-sticky bottom-0 bg-light py-3 d-flex gap-2"><button class="btn btn-outline-primary flex-fill" name="action" value="save">Save Draft</button><button class="btn btn-success flex-fill" name="action" value="submit" onclick="return confirm('Submit and lock your scores?')">Submit & Lock</button></div><?php endif;?></form></main></body></html>
