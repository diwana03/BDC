<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\AutomaticJudgeBrowserService;
use App\Services\JudgeLinkDeliveryService;
use App\Services\ScoringJudgeEmergencyService;

Auth::requireAdmin();
$pdo=Database::connection();
$roundId=(int)($_GET['round_id']??$_POST['round_id']??0);
$userId=(int)(Auth::user()['id']??0);

function ensureAutomaticJudgeSessionStorage(PDO $pdo):void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_scoring_judge_sessions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        round_id BIGINT UNSIGNED NOT NULL,
        judge_id BIGINT UNSIGNED NOT NULL,
        token_hash CHAR(64) NOT NULL,
        token_hint VARCHAR(16) NULL,
        status VARCHAR(24) NOT NULL DEFAULT 'not_started',
        opened_at DATETIME NULL,
        last_saved_at DATETIME NULL,
        submitted_at DATETIME NULL,
        unlocked_at DATETIME NULL,
        unlocked_by BIGINT UNSIGNED NULL,
        unlock_reason VARCHAR(500) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_bdc_judge_session_judge (judge_id),
        UNIQUE KEY uq_bdc_judge_session_token (token_hash),
        KEY idx_bdc_judge_session_round (round_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

try{
    ensureAutomaticJudgeSessionStorage($pdo);
    $roundStmt=$pdo->prepare("SELECT r.*,e.name event_name FROM bdc_scoring_rounds r JOIN bdc_events e ON e.id=r.event_id WHERE r.id=:round LIMIT 1");
    $roundStmt->execute(['round'=>$roundId]);
    $round=$roundStmt->fetch();
    if(!$round||($round['scoring_mode']??'')!=='automated'){
        http_response_code(404);
        exit('Automatic scoring round not found.');
    }
}catch(Throwable $e){
    http_response_code(200);
    ?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;font-family:Arial,sans-serif;background:#fff;color:#172033}.alert{padding:14px;border:1px solid #f0b7b7;background:#fff1f1;border-radius:10px;color:#8d1111}</style></head><body><div class="alert"><strong>Judge Live Scoring is not ready.</strong><br><?=e($e->getMessage())?></div></body></html><?php
    exit;
}

if(isset($_GET['status'])){
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    try{
        echo json_encode([
            'judges'=>AutomaticJudgeBrowserService::progress($pdo,$roundId),
            'all_submitted'=>AutomaticJudgeBrowserService::allSubmitted($pdo,$roundId)
        ],JSON_UNESCAPED_SLASHES);
    }catch(Throwable $e){
        echo json_encode(['judges'=>[],'all_submitted'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_SLASHES);
    }
    exit;
}

$message='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Invalid security token.');
        $action=(string)($_POST['action']??'');
        $judgeId=(int)($_POST['judge_id']??0);
        if($action==='regenerate_copy'){
            $token=AutomaticJudgeBrowserService::regenerate($pdo,$roundId,$judgeId);
            $_SESSION['automatic_judge_tokens'][$judgeId]=$token;
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok'=>true,'url'=>AutomaticJudgeBrowserService::publicUrl($token)],JSON_UNESCAPED_SLASHES);
            exit;
        }elseif($action==='regenerate_open'){
            $token=AutomaticJudgeBrowserService::regenerate($pdo,$roundId,$judgeId);
            $_SESSION['automatic_judge_tokens'][$judgeId]=$token;
            header('Location: '.AutomaticJudgeBrowserService::publicUrl($token));
            exit;
        }elseif($action==='regenerate'){
            $token=AutomaticJudgeBrowserService::regenerate($pdo,$roundId,$judgeId);
            $_SESSION['automatic_judge_tokens'][$judgeId]=$token;
            $message='Judge link regenerated.';
        }elseif($action==='unlock'){
            $reason=trim((string)($_POST['reason']??''));
            AutomaticJudgeBrowserService::unlock($pdo,$roundId,$judgeId,$userId,$reason);
            $message='Judge scores unlocked. The judge can edit and submit again.';
            $audit=$pdo->prepare('INSERT INTO bdc_scoring_audit(round_id,user_id,action,details_json) VALUES(:round,:user,:action,:details)');
            $audit->execute(['round'=>$roundId,'user'=>$userId?:null,'action'=>'automatic_judge_scores_unlocked','details'=>json_encode(['judge_id'=>$judgeId,'reason'=>$reason],JSON_UNESCAPED_UNICODE)]);
        }elseif(in_array($action,['send_email','open_whatsapp'],true)){
            $contact=JudgeLinkDeliveryService::contact($pdo,$judgeId,$roundId,false);
            $token=(string)($_SESSION['automatic_judge_tokens'][$judgeId]??'');
            if($token===''){$token=AutomaticJudgeBrowserService::regenerate($pdo,$roundId,$judgeId);$_SESSION['automatic_judge_tokens'][$judgeId]=$token;}
            $judgeUrl=AutomaticJudgeBrowserService::publicUrl($token);$roundLabel=ucfirst((string)$round['round_type']);$categoryLabel=ucwords(str_replace('_',' ',(string)$round['division']));
            $shareText="Hi ".$contact['judge_name'].",\n\nHere is your secure BDC judging link for ".$round['event_name']." — ".$categoryLabel." — ".$roundLabel.".\n\n".$judgeUrl."\n\nPlease review the judging criteria, complete your scoring independently, and press Submit & Lock when finished. Keep this secure link confidential.";
            if($action==='send_email'){JudgeLinkDeliveryService::sendEmail($pdo,$contact,$roundId,false,'IMPORTANT — BDC Judge Scoring Link — '.$round['event_name'],"IMPORTANT: Secure judging access\n\n".$shareText,$userId);$message='Email accepted by the website mail server for '.$contact['judge_name'].'.';}
            else{$target=JudgeLinkDeliveryService::whatsappUrl($pdo,$contact,$roundId,false,$shareText,$userId);header('Location: '.$target);exit;}
        }elseif($action==='emergency_remove'){
            if(!Auth::canOverrideCompletedScores())throw new RuntimeException('Only a Scorer, Master Scorer or Super Admin can remove a committed judge.');
            $removed=ScoringJudgeEmergencyService::remove($pdo,$roundId,$judgeId,false,$userId,(string)($_POST['reason']??''),(string)($_POST['confirmation']??''));
            unset($_SESSION['automatic_judge_tokens'][$judgeId]);
            $message=$removed['judge_name'].' removed safely. Backup #'.$removed['backup_id'].' was created and calculated results were cleared for recalculation.'.($removed['replacement_chief']?' '.$removed['replacement_chief'].' is now Chief Judge.':'');
        }
    }catch(Throwable $e){$error=$e->getMessage();}
}

try{
    $judges=AutomaticJudgeBrowserService::syncRound($pdo,$roundId);
    foreach($judges as &$judge){
        $judge['contact']=JudgeLinkDeliveryService::contact($pdo,(int)$judge['id'],$roundId,false);
        if($judge['plain_token']!=='')$_SESSION['automatic_judge_tokens'][(int)$judge['id']]=$judge['plain_token'];
        $judge['token']=$_SESSION['automatic_judge_tokens'][(int)$judge['id']]??'';
        $judge['url']=$judge['token']!==''?AutomaticJudgeBrowserService::publicUrl($judge['token']):'';
        if($judge['url']!==''){
            $roundLabel=ucfirst((string)$round['round_type']);
            $categoryLabel=ucwords(str_replace('_',' ',(string)$round['division']));
            $shareText="Hi ".$judge['judge_name'].",\n\nHere is your secure BDC judging link for ".$round['event_name']." — ".$categoryLabel." — ".$roundLabel.".\n\n".$judge['url']."\n\nPlease complete your scoring and press Submit when finished. After submission your scores are locked.";
        }
    }
    unset($judge);
    $progress=AutomaticJudgeBrowserService::progress($pdo,$roundId);
}catch(Throwable $e){
    $judges=[];$progress=[];$error=$e->getMessage();
}

$byId=[];foreach($progress as $row)$byId[(int)$row['judge_id']]=$row;
$csrf=Csrf::token();
$category=ucwords(str_replace('_',' ',(string)$round['division']));
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:#fff;color:#172033}.wrap{padding:0}.head{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:12px}.head h2{font-size:18px;margin:0}.small{font-size:12px;color:#667085}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.judge{border:1px solid #dfe3e8;border-radius:12px;padding:13px}.row{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}.name{font-weight:800}.chief{font-size:11px;background:#ffe49a;padding:3px 7px;border-radius:999px}.status{font-size:12px;font-weight:800;padding:4px 8px;border-radius:999px;background:#eef1f5}.status.submitted{background:#dff4e6;color:#176b36}.status.scoring{background:#fff0c7;color:#815d00}.bar{height:8px;background:#edf0f4;border-radius:999px;overflow:hidden;margin:10px 0 6px}.bar i{display:block;height:100%;background:#1774ff}.meta{font-size:12px;color:#667085}.actions{display:flex;flex-wrap:wrap;gap:7px;margin-top:10px}.btn{border:1px solid #bfc6d0;background:#fff;border-radius:8px;padding:7px 9px;font-weight:700;font-size:12px;cursor:pointer;text-decoration:none;color:#172033}.btn.primary{background:#1774ff;color:#fff;border-color:#1774ff}.btn.whatsapp{background:#e9f8ee;border-color:#8ed4a2;color:#126b2d}.btn.email{background:#eef4ff;border-color:#9bbcf8;color:#174ea6}.btn.danger{border-color:#d33;color:#b11212}.alert{padding:9px 11px;border-radius:8px;margin-bottom:10px;font-size:13px}.ok{background:#e4f6ea;color:#176b36}.bad{background:#ffe7e7;color:#8d1111}.waiting{background:#f4f6f9;color:#667085;border:1px solid #dfe3e8}.all{background:#e8f6ed;border:1px solid #add9bb;padding:12px;border-radius:10px;font-weight:800;margin-top:12px}.url{display:flex;gap:5px;margin-top:8px;flex-wrap:wrap}.url input{flex:1;min-width:260px;padding:7px;border:1px solid #ccd2da;border-radius:7px;font-size:11px}.unlock{margin-top:8px;display:flex;gap:5px}.unlock input{flex:1;padding:7px;border:1px solid #ccd2da;border-radius:7px;font-size:12px}.emergency{margin-top:10px;border-top:1px solid #f1d1d1;padding-top:8px}.emergency summary{cursor:pointer;color:#9f1c1c;font-weight:800;font-size:12px}.emergency form{display:grid;grid-template-columns:1fr 160px auto;gap:6px;margin-top:8px}.emergency input{min-width:0;padding:7px;border:1px solid #d7a9a9;border-radius:7px;font-size:12px}@media(max-width:900px){.grid{grid-template-columns:1fr}}@media(max-width:640px){.url input{min-width:100%;width:100%}.emergency form{grid-template-columns:1fr}.emergency .btn{width:100%}}
</style></head><body><div class="wrap"><div class="head"><div><h2>Judge Browser Scoring</h2><div class="small"><?=e($category)?> · <?=e(strtoupper((string)$round['round_type']))?> · Live progress</div></div><div class="small" id="lastRefresh">Live</div></div>
<?php if($message):?><div class="alert ok"><?=e($message)?></div><?php endif;?><?php if($error):?><div class="alert bad"><?=e($error)?></div><?php endif;?>
<?php if(!$judges):?><div class="alert waiting">Add and save at least 3 judges, then add active Leaders and Followers. Secure judge links will appear here automatically.</div><?php else:?><div class="grid"><?php foreach($judges as $judge):$p=$byId[(int)$judge['id']]??[];$status=(string)($p['session_status']??'not_started');?><div class="judge" data-judge="<?=(int)$judge['id']?>"><div class="row"><div><span class="name">J<?=(int)$judge['judge_order']?> · <?=e($judge['judge_name'])?></span><?php if((int)$judge['is_chief']===1):?> <span class="chief">★ CHIEF</span><?php endif;?><div class="meta"><?=e(ucfirst((string)$judge['scoring_scope']))?> panel</div></div><span class="status <?=e($status)?>" data-status><?=e(ucwords(str_replace('_',' ',$status)))?></span></div><div class="bar"><i data-bar style="width:<?=(int)($p['percent']??0)?>%"></i></div><div class="meta" data-progress><?=(int)($p['done']??0)?> / <?=(int)($p['total']??0)?> scored · <?=(int)($p['percent']??0)?>%</div>
<?php $hasWhatsapp=trim((string)($judge['contact']['whatsapp']?:$judge['contact']['phone']??''))!=='';$hasEmail=trim((string)($judge['contact']['email']??''))!== '';?><?php if($judge['url']!==''):?><div class="url"><input id="judgeUrl<?=(int)$judge['id']?>" readonly value="<?=e($judge['url'])?>"><button class="btn" type="button" onclick="navigator.clipboard.writeText(document.getElementById('judgeUrl<?=(int)$judge['id']?>').value)">Copy Link</button><?php if($hasWhatsapp):?><form method="post" target="_blank"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="judge_id" value="<?=(int)$judge['id']?>"><button class="btn whatsapp" name="action" value="open_whatsapp">Send WhatsApp</button></form><?php endif;?><?php if($hasEmail):?><form method="post"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="judge_id" value="<?=(int)$judge['id']?>"><button class="btn email" name="action" value="send_email">Send Email</button></form><?php endif;?><a class="btn primary" href="<?=e($judge['url'])?>" target="_blank" rel="noopener">Open</a></div><?php if(!$hasWhatsapp&&!$hasEmail):?><div class="meta" style="margin-top:5px">Contact information missing</div><?php else:?><div class="meta" style="margin-top:5px">Database contact: <?=e((string)($judge['contact']['email']?:$judge['contact']['whatsapp']?:$judge['contact']['phone']))?></div><?php endif;?><?php else:?><div class="meta" style="margin-top:8px">The previous secure URL is hidden. Choosing an action creates one replacement link.</div><div class="url"><button class="btn" type="button" onclick="regenerateAndCopy(<?=(int)$judge['id']?>,this)">Copy New Link</button><form method="post" target="_blank"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="judge_id" value="<?=(int)$judge['id']?>"><button class="btn primary" name="action" value="regenerate_open">Open New Link</button></form><?php if($hasWhatsapp):?><form method="post" target="_blank"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="judge_id" value="<?=(int)$judge['id']?>"><button class="btn whatsapp" name="action" value="open_whatsapp">Send WhatsApp</button></form><?php endif;?><?php if($hasEmail):?><form method="post"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="judge_id" value="<?=(int)$judge['id']?>"><button class="btn email" name="action" value="send_email">Send Email</button></form><?php endif;?></div><?php endif;?><div class="actions"><form method="post"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="judge_id" value="<?=(int)$judge['id']?>"><input type="hidden" name="action" value="regenerate"><button class="btn" type="submit">Regenerate Link</button></form></div><?php if($status==='submitted'):?><form method="post" class="unlock" onsubmit="return confirm('Reopen scoring for this judge? Existing marks and the secure link will be retained, and this action will be audited.')"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="judge_id" value="<?=(int)$judge['id']?>"><input type="hidden" name="action" value="unlock"><input name="reason" maxlength="500" required placeholder="Reason for reopening"><button class="btn danger" type="submit">Reopen Scoring</button></form><?php endif;?></div><?php endforeach;?></div><div class="all" id="allSubmitted" style="display:<?=AutomaticJudgeBrowserService::allSubmitted($pdo,$roundId)?'block':'none'?>">✓ ALL JUDGES SUBMITTED AND LOCKED — results can now be calculated.</div><?php endif;?></div>
<script>
async function regenerateAndCopy(judgeId,button){const original=button.textContent;button.disabled=true;button.textContent='Creating…';try{const body=new FormData();body.set('_csrf',<?=json_encode($csrf)?>);body.set('round_id',<?=json_encode($roundId)?>);body.set('judge_id',String(judgeId));body.set('action','regenerate_copy');const response=await fetch(location.href,{method:'POST',body,credentials:'same-origin'}),result=await response.json();if(!result.ok||!result.url)throw new Error(result.error||'Link creation failed');await navigator.clipboard.writeText(result.url);button.textContent='Copied';setTimeout(()=>location.reload(),700)}catch(error){button.disabled=false;button.textContent=original;alert(error.message)}}
<?php if(Auth::canOverrideCompletedScores()):?>
document.querySelectorAll('.judge[data-judge]').forEach(card=>{
 const details=document.createElement('details');details.className='emergency';
 details.innerHTML='<summary>Emergency panel change · Remove judge</summary><form method="post"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="judge_id" value="'+card.dataset.judge+'"><input type="hidden" name="action" value="emergency_remove"><input name="reason" maxlength="500" minlength="8" required placeholder="Emergency reason"><input name="confirmation" required placeholder="Type REMOVE JUDGE"><button class="btn danger" type="submit">Remove Judge Safely</button></form><div class="meta" style="margin-top:5px">Protected backup first · other judge scores stay · results require recalculation.</div>';
 details.querySelector('form').addEventListener('submit',event=>{if(!confirm('Remove this judge from the committed panel? A protected backup will be created and calculated results will be cleared.'))event.preventDefault();});
 card.appendChild(details);
});
<?php endif;?>
function poll(){if(document.hidden){setTimeout(poll,5000);return;}fetch('?round_id=<?=$roundId?>&status=1&_='+Date.now(),{cache:'no-store'}).then(r=>r.status===429?null:r.json()).then(data=>{if(!data)return;(data.judges||[]).forEach(j=>{const el=document.querySelector('[data-judge="'+j.judge_id+'"]');if(!el)return;const s=el.querySelector('[data-status]');if(!s.classList.contains('submitted')&&j.session_status==='submitted'){location.reload();return}s.textContent=j.session_status.replaceAll('_',' ').replace(/\b\w/g,c=>c.toUpperCase());s.className='status '+j.session_status;el.querySelector('[data-bar]').style.width=j.percent+'%';el.querySelector('[data-progress]').textContent=j.done+' / '+j.total+' scored · '+j.percent+'%';});const all=document.getElementById('allSubmitted');if(all)all.style.display=data.all_submitted?'block':'none';document.getElementById('lastRefresh').textContent='Updated '+new Date().toLocaleTimeString();}).catch(()=>{}).finally(()=>setTimeout(poll,5000));}setTimeout(poll,1000);
</script></body></html>
