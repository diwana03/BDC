<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\TestAutomaticJudgeService;

Auth::requireAdmin();
$pdo=Database::connection();
$roundId=(int)($_GET['round_id']??$_POST['round_id']??0);
if($roundId<1){http_response_code(400);exit('Open a test round first.');}
TestAutomaticJudgeService::ensureSchema($pdo);
$mode=(string)($_POST['test_mode']??$_GET['test_mode']??$_SESSION['bdc_test_scoring_mode']??'automated');
if(!in_array($mode,['manual','automated'],true))$mode='automated';
$redirect=url('admin/scoring-tests/dashboard.php?legacy=1&test_mode='.$mode.'&round_id='.$roundId);

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    if(!Csrf::verify($_POST['_csrf']??null)){http_response_code(419);exit('Invalid security token.');}
    $action=(string)($_POST['action']??'');
    try{
        if($action==='regenerate_link'){
            $judgeId=(int)($_POST['judge_id']??0);
            $token=TestAutomaticJudgeService::regenerate($pdo,$roundId,$judgeId);
            $_SESSION['bdc_test_auto_urls'][$roundId][$judgeId]=TestAutomaticJudgeService::publicUrl($token);
        }elseif($action==='delete_judge'){
            $judgeId=(int)($_POST['judge_id']??0);
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM bdc_test_scoring_marks WHERE round_id=:r AND judge_id=:j')->execute(['r'=>$roundId,'j'=>$judgeId]);
            $pdo->prepare('DELETE FROM bdc_test_scoring_judge_sessions WHERE round_id=:r AND judge_id=:j')->execute(['r'=>$roundId,'j'=>$judgeId]);
            $pdo->prepare('DELETE FROM bdc_test_scoring_judges WHERE round_id=:r AND id=:j')->execute(['r'=>$roundId,'j'=>$judgeId]);
            $pdo->commit();
            unset($_SESSION['bdc_test_auto_urls'][$roundId][$judgeId]);
        }elseif($action==='delete_all_judges'){
            $pdo->beginTransaction();
            TestAutomaticJudgeService::clearJudgeSessions($pdo,$roundId);
            $pdo->prepare('DELETE FROM bdc_test_scoring_marks WHERE round_id=:r')->execute(['r'=>$roundId]);
            $pdo->prepare('DELETE FROM bdc_test_scoring_judges WHERE round_id=:r')->execute(['r'=>$roundId]);
            $pdo->commit();
            unset($_SESSION['bdc_test_auto_urls'][$roundId]);
        }elseif($action==='delete_entry'){
            $entryId=(int)($_POST['entry_id']??0);
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM bdc_test_scoring_marks WHERE round_id=:r AND entry_id=:e')->execute(['r'=>$roundId,'e'=>$entryId]);
            $pdo->prepare('DELETE FROM bdc_test_scoring_results WHERE round_id=:r AND entry_id=:e')->execute(['r'=>$roundId,'e'=>$entryId]);
            $pdo->prepare('DELETE FROM bdc_test_scoring_entries WHERE round_id=:r AND id=:e')->execute(['r'=>$roundId,'e'=>$entryId]);
            $pdo->commit();
        }elseif($action==='delete_all_entries'){
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM bdc_test_scoring_results WHERE round_id=:r')->execute(['r'=>$roundId]);
            $pdo->prepare('DELETE FROM bdc_test_scoring_marks WHERE round_id=:r')->execute(['r'=>$roundId]);
            $pdo->prepare('DELETE FROM bdc_test_scoring_entries WHERE round_id=:r')->execute(['r'=>$roundId]);
            $pdo->commit();
        }elseif($action==='clear_round'){
            $pdo->beginTransaction();
            TestAutomaticJudgeService::clearJudgeSessions($pdo,$roundId);
            foreach(['bdc_test_scoring_results','bdc_test_scoring_marks','bdc_test_scoring_judges','bdc_test_scoring_entries'] as $table){
                $pdo->prepare("DELETE FROM {$table} WHERE round_id=:r")->execute(['r'=>$roundId]);
            }
            $pdo->prepare("UPDATE bdc_test_scoring_rounds SET status='draft',generated_version=0,chief_judge_id=NULL WHERE id=:r")->execute(['r'=>$roundId]);
            $pdo->commit();
            unset($_SESSION['bdc_test_auto_urls'][$roundId]);
        }
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        $_SESSION['bdc_test_auto_error']=$e->getMessage();
    }
    header('Location: '.$redirect,true,303);exit;
}

$items=TestAutomaticJudgeService::syncRound($pdo,$roundId);
foreach($items as $item){
    $judgeId=(int)$item['id'];
    if(($item['plain_token']??'')!==''){
        $_SESSION['bdc_test_auto_urls'][$roundId][$judgeId]=TestAutomaticJudgeService::publicUrl((string)$item['plain_token']);
    }
}
$progress=TestAutomaticJudgeService::progress($pdo,$roundId);
$urls=$_SESSION['bdc_test_auto_urls'][$roundId]??[];
$error=(string)($_SESSION['bdc_test_auto_error']??'');unset($_SESSION['bdc_test_auto_error']);
$csrf=Csrf::token();
?>
<div class="card shadow-sm mb-4" id="automaticJudgeLivePanel">
 <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
  <div><strong>Judge Live Scoring</strong><div class="small text-muted">Automatic Test · Judges score from their own secure browser URL.</div></div>
  <div class="d-flex gap-2">
   <form method="post" action="<?=e(url('admin/scoring-tests/automatic-inline.php'))?>" onsubmit="return confirm('Delete all test judges and their marks?')">
    <input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="test_mode" value="automated"><input type="hidden" name="action" value="delete_all_judges">
    <button class="btn btn-sm btn-outline-danger">Delete All Judges</button>
   </form>
   <form method="post" action="<?=e(url('admin/scoring-tests/automatic-inline.php'))?>" onsubmit="return confirm('Clear judges, competitors, marks and results for this TEST round?')">
    <input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="test_mode" value="automated"><input type="hidden" name="action" value="clear_round">
    <button class="btn btn-sm btn-danger">Clear Entire Test Round</button>
   </form>
  </div>
 </div>
 <div class="card-body">
  <?php if($error!==''):?><div class="alert alert-danger py-2"><?=e($error)?></div><?php endif;?>
  <?php if(!$progress):?><div class="alert alert-warning mb-0">Save at least 3 judges above. Secure judge links will appear here automatically.</div><?php else:?>
  <div class="table-responsive"><table class="table table-sm align-middle mb-0">
   <thead><tr><th>Judge</th><th>Scope</th><th>Status</th><th style="min-width:180px">Progress</th><th>Secure Judge URL</th><th>Actions</th></tr></thead>
   <tbody>
   <?php foreach($progress as $row):$jid=(int)$row['judge_id'];$status=(string)$row['session_status'];$url=(string)($urls[$jid]??'');?>
    <tr>
     <td><strong><?=e($row['judge_name'])?></strong><?=(int)$row['is_chief']?' <span class="badge text-bg-dark">Chief</span>':''?></td>
     <td><?=e(ucfirst((string)$row['scoring_scope']))?></td>
     <td><?php $badge=$status==='submitted'?'success':($status==='scoring'?'primary':'secondary');?><span class="badge text-bg-<?=$badge?>"><?=e(ucwords(str_replace('_',' ',$status)))?></span></td>
     <td><div class="progress" style="height:8px"><div class="progress-bar" style="width:<?=(int)$row['percent']?>%"></div></div><div class="small mt-1"><?=(int)$row['done']?> / <?=(int)$row['total']?> scored · <?=(int)$row['percent']?>%</div></td>
     <td>
      <?php if($url!==''):?><div class="input-group input-group-sm" style="min-width:320px"><input class="form-control" readonly value="<?=e($url)?>"><button type="button" class="btn btn-outline-primary" onclick="navigator.clipboard.writeText(this.previousElementSibling.value)">Copy</button><a class="btn btn-outline-dark" target="_blank" href="<?=e($url)?>">Open</a></div>
      <?php else:?><span class="text-muted small">Existing token hidden. Regenerate to reveal a new URL.</span><?php endif;?>
     </td>
     <td><div class="d-flex gap-1 flex-wrap">
      <form method="post" action="<?=e(url('admin/scoring-tests/automatic-inline.php'))?>"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="test_mode" value="automated"><input type="hidden" name="judge_id" value="<?=$jid?>"><input type="hidden" name="action" value="regenerate_link"><button class="btn btn-sm btn-outline-primary">Regenerate</button></form>
      <form method="post" action="<?=e(url('admin/scoring-tests/automatic-inline.php'))?>" onsubmit="return confirm('Delete this judge and their test marks?')"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="test_mode" value="automated"><input type="hidden" name="judge_id" value="<?=$jid?>"><input type="hidden" name="action" value="delete_judge"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
     </div></td>
    </tr>
   <?php endforeach;?>
   </tbody>
  </table></div>
  <div class="small text-muted mt-3">Submitted judges are locked on their judge URL. The BDC calculation engine remains the same; only score entry is automatic/browser based.</div>
  <?php endif;?>
 </div>
</div>
<script>
(function(){
 const endpoint=<?=json_encode(url('admin/scoring-tests/automatic-inline.php?round_id='.$roundId.'&test_mode=automated'),JSON_UNESCAPED_SLASHES)?>;
 window.__bdcAutomaticPanelEndpoint=endpoint;
 setTimeout(function refresh(){
   fetch(endpoint,{headers:{'X-Requested-With':'XMLHttpRequest'},cache:'no-store'}).then(r=>r.text()).then(html=>{
     const current=document.getElementById('automaticJudgeLivePanel');
     if(!current)return;
     const box=document.createElement('div');box.innerHTML=html;
     const fresh=box.querySelector('#automaticJudgeLivePanel');if(fresh)current.replaceWith(fresh);
   }).catch(()=>{});
   setTimeout(refresh,5000);
 },5000);
})();
</script>
