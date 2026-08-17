<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\AutomaticJudgeBrowserService;

Auth::requireAdmin();
$pdo=Database::connection();$roundId=(int)($_GET['round_id']??$_POST['round_id']??0);$userId=(int)(Auth::user()['id']??0);
$roundStmt=$pdo->prepare("SELECT r.*,e.name event_name FROM bdc_scoring_rounds r JOIN bdc_events e ON e.id=r.event_id WHERE r.id=:round LIMIT 1");$roundStmt->execute(['round'=>$roundId]);$round=$roundStmt->fetch();
if(!$round||($round['scoring_mode']??'')!=='automated'){http_response_code(404);exit('Automatic scoring round not found.');}
$confirmed=AutomaticJudgeBrowserService::isSetupConfirmed($pdo,$roundId);

if(isset($_GET['status'])){
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
    echo json_encode(['confirmed'=>$confirmed,'judges'=>$confirmed?AutomaticJudgeBrowserService::progress($pdo,$roundId):[],'all_submitted'=>$confirmed&&AutomaticJudgeBrowserService::allSubmitted($pdo,$roundId)],JSON_UNESCAPED_SLASHES);exit;
}
$message='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Invalid security token.');
        if(!$confirmed)throw new RuntimeException('Confirm judges and competitors before managing judge links.');
        $action=(string)($_POST['action']??'');$judgeId=(int)($_POST['judge_id']??0);
        if($action==='regenerate'){
            $token=AutomaticJudgeBrowserService::regenerate($pdo,$roundId,$judgeId);$_SESSION['automatic_judge_tokens'][$judgeId]=$token;$message='Judge link regenerated.';
        }elseif($action==='unlock'){
            if(!Auth::isSuperAdmin())throw new RuntimeException('Only Super Admin can unlock submitted judge scores.');
            $reason=trim((string)($_POST['reason']??''));AutomaticJudgeBrowserService::unlock($pdo,$roundId,$judgeId,$userId,$reason);$message='Judge scores unlocked.';
            $audit=$pdo->prepare('INSERT INTO bdc_scoring_audit(round_id,user_id,action,details_json) VALUES(:round,:user,:action,:details)');$audit->execute(['round'=>$roundId,'user'=>$userId?:null,'action'=>'automatic_judge_scores_unlocked','details'=>json_encode(['judge_id'=>$judgeId,'reason'=>$reason],JSON_UNESCAPED_UNICODE)]);
        }
    }catch(Throwable $e){$error=$e->getMessage();}
}
$confirmed=AutomaticJudgeBrowserService::isSetupConfirmed($pdo,$roundId);
$judges=$confirmed?AutomaticJudgeBrowserService::syncRound($pdo,$roundId):[];
foreach($judges as &$judge){if($judge['plain_token']!=='')$_SESSION['automatic_judge_tokens'][(int)$judge['id']]=$judge['plain_token'];$judge['token']=$_SESSION['automatic_judge_tokens'][(int)$judge['id']]??'';$judge['url']=$judge['token']!==''?AutomaticJudgeBrowserService::publicUrl($judge['token']):'';}unset($judge);
$progress=$confirmed?AutomaticJudgeBrowserService::progress($pdo,$roundId):[];$byId=[];foreach($progress as $row)$byId[(int)$row['judge_id']]=$row;$csrf=Csrf::token();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;color:#172033;background:#fff}.head{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}.head h2{font-size:18px;margin:0}.small{font-size:12px;color:#667085}.notice{padding:16px;border-radius:10px;background:#fff7dc;border:1px solid #efd980;color:#6b5600}.grid{display:grid;gap:10px}.judge{border:1px solid #dfe3e8;border-radius:12px;padding:13px}.row{display:flex;justify-content:space-between;gap:10px}.name{font-weight:800}.chief{font-size:11px;background:#ffe49a;padding:3px 7px;border-radius:999px}.status{font-size:12px;font-weight:800;padding:4px 8px;border-radius:999px;background:#eef1f5}.status.submitted{background:#dff4e6;color:#176b36}.status.scoring{background:#fff0c7;color:#815d00}.bar{height:8px;background:#edf0f4;border-radius:999px;overflow:hidden;margin:10px 0 6px}.bar i{display:block;height:100%;background:#1774ff}.meta{font-size:12px;color:#667085}.url{display:flex;gap:6px;margin-top:9px}.url input{flex:1;min-width:0;padding:7px;border:1px solid #ccd2da;border-radius:7px;font-size:11px}.btn{border:1px solid #bfc6d0;background:#fff;border-radius:8px;padding:7px 9px;font-weight:700;font-size:12px;cursor:pointer;text-decoration:none;color:#172033}.btn.primary{background:#1774ff;color:#fff;border-color:#1774ff}.btn.danger{color:#b11212;border-color:#d33}.actions{display:flex;gap:7px;margin-top:8px;flex-wrap:wrap}.all{background:#e8f6ed;border:1px solid #add9bb;padding:12px;border-radius:10px;font-weight:800;margin-top:12px}.alert{padding:9px 11px;border-radius:8px;margin-bottom:10px}.ok{background:#e4f6ea;color:#176b36}.bad{background:#ffe7e7;color:#8d1111}.unlock{display:flex;gap:5px;margin-top:8px}.unlock input{flex:1;padding:7px;border:1px solid #ccd2da;border-radius:7px}
</style></head><body><div class="head"><div><h2>Judge Browser Scoring</h2><div class="small">Secure links appear only after setup confirmation.</div></div><div class="small" id="lastRefresh">Live</div></div>
<?php if($message):?><div class="alert ok"><?=e($message)?></div><?php endif;?><?php if($error):?><div class="alert bad"><?=e($error)?></div><?php endif;?>
<?php if(!$confirmed):?><div class="notice"><strong>Waiting for setup confirmation.</strong><br>Finish the judge list and Registration Desk competitors above, then press <strong>Confirm Judges &amp; Competitors</strong>. No judge links have been generated yet.</div><?php else:?><div class="grid"><?php foreach($judges as $judge):$p=$byId[(int)$judge['id']]??[];$status=(string)($p['session_status']??'not_started');?><div class="judge" data-judge="<?=(int)$judge['id']?>"><div class="row"><div><span class="name">J<?=(int)$judge['judge_order']?> · <?=e($judge['judge_name'])?></span><?php if((int)$judge['is_chief']===1):?> <span class="chief">★ CHIEF</span><?php endif;?><div class="meta"><?=e(ucfirst((string)$judge['scoring_scope']))?> panel</div></div><span class="status <?=e($status)?>" data-status><?=e(ucwords(str_replace('_',' ',$status)))?></span></div><div class="bar"><i data-bar style="width:<?=(int)($p['percent']??0)?>%"></i></div><div class="meta" data-progress><?= (int)($p['done']??0) ?> / <?= (int)($p['total']??0) ?> scored · <?= (int)($p['percent']??0) ?>%</div><?php if($judge['url']!==''):?><div class="url"><input id="u<?=(int)$judge['id']?>" readonly value="<?=e($judge['url'])?>"><button class="btn" type="button" onclick="navigator.clipboard.writeText(document.getElementById('u<?=(int)$judge['id']?>').value)">Copy Link</button><a class="btn primary" href="<?=e($judge['url'])?>" target="_blank">Open</a></div><?php endif;?><div class="actions"><form method="post"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="judge_id" value="<?=(int)$judge['id']?>"><input type="hidden" name="action" value="regenerate"><button class="btn" type="submit">Regenerate Link</button></form></div><?php if(Auth::isSuperAdmin()&&$status==='submitted'):?><form method="post" class="unlock"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="judge_id" value="<?=(int)$judge['id']?>"><input type="hidden" name="action" value="unlock"><input name="reason" required placeholder="Reason for unlock"><button class="btn danger" type="submit">Unlock</button></form><?php endif;?></div><?php endforeach;?></div><div class="all" id="allSubmitted" style="display:<?=AutomaticJudgeBrowserService::allSubmitted($pdo,$roundId)?'block':'none'?>">✓ ALL JUDGES SUBMITTED AND LOCKED — results can now be calculated.</div><?php endif;?>
<script>function poll(){if(document.hidden){setTimeout(poll,5000);return;}fetch('?round_id=<?=$roundId?>&status=1',{cache:'no-store'}).then(r=>r.status===429?null:r.json()).then(d=>{if(!d)return;if(!d.confirmed)return;if(!document.querySelector('[data-judge]')){location.reload();return;}d.judges.forEach(j=>{const el=document.querySelector('[data-judge="'+j.judge_id+'"]');if(!el)return;const s=el.querySelector('[data-status]');s.textContent=j.session_status.replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase());s.className='status '+j.session_status;el.querySelector('[data-bar]').style.width=j.percent+'%';el.querySelector('[data-progress]').textContent=j.done+' / '+j.total+' scored · '+j.percent+'%';});document.getElementById('allSubmitted').style.display=d.all_submitted?'block':'none';document.getElementById('lastRefresh').textContent='Updated '+new Date().toLocaleTimeString();}).catch(()=>{}).finally(()=>setTimeout(poll,5000));}setTimeout(poll,1500);</script></body></html>
