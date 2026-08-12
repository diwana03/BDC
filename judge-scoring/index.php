<?php
declare(strict_types=1);

require dirname(__DIR__).'/bootstrap.php';

use App\Core\Database;
use App\Services\AutomaticJudgeBrowserService;

$pdo=Database::connection();
$token=trim((string)($_GET['token']??$_POST['token']??''));
$session=AutomaticJudgeBrowserService::byToken($pdo,$token);
if(!$session||($session['scoring_mode']??'')!=='automated'){http_response_code(404);exit('Judge scoring link not found or expired.');}
$sessionId=(int)$session['id'];$roundId=(int)$session['round_id'];$judgeId=(int)$session['judge_id'];
AutomaticJudgeBrowserService::markOpened($pdo,$sessionId);
$locked=(string)$session['status']==='submitted';$isFinal=(string)$session['round_type']==='final';$error='';$notice='';

$roundStmt=$pdo->prepare('SELECT yes_count,yes_weight,alt1_weight,alt2_weight,alt3_weight FROM bdc_scoring_rounds WHERE id=:round');
$roundStmt->execute(['round'=>$roundId]);$weights=$roundStmt->fetch()?:['yes_count'=>10,'yes_weight'=>10,'alt1_weight'=>4.5,'alt2_weight'=>4.3,'alt3_weight'=>4.2];
$yesLimit=max(1,(int)($weights['yes_count']??10));

function judgeJson(array $data,int $status=200):never{http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);exit;}

function heatsSelectionState(PDO $pdo,int $roundId,int $judgeId,string $role):array
{
    $stmt=$pdo->prepare("SELECT
        SUM(CASE WHEN m.mark_type='yes' THEN 1 ELSE 0 END) yes_count,
        SUM(CASE WHEN m.mark_type='alt' AND m.alt_rank=1 THEN 1 ELSE 0 END) alt1_count,
        SUM(CASE WHEN m.mark_type='alt' AND m.alt_rank=2 THEN 1 ELSE 0 END) alt2_count,
        SUM(CASE WHEN m.mark_type='alt' AND m.alt_rank=3 THEN 1 ELSE 0 END) alt3_count
        FROM bdc_scoring_marks m
        JOIN bdc_scoring_entries e ON e.id=m.entry_id
        WHERE m.round_id=:round AND m.judge_id=:judge AND e.dance_role=:role AND e.entry_status='active'");
    $stmt->execute(['round'=>$roundId,'judge'=>$judgeId,'role'=>$role]);
    $row=$stmt->fetch()?:[];
    return ['yes'=>(int)($row['yes_count']??0),'A1'=>(int)($row['alt1_count']??0),'A2'=>(int)($row['alt2_count']??0),'A3'=>(int)($row['alt3_count']??0)];
}

function saveHeatsJudgeMark(PDO $pdo,int $roundId,int $judgeId,int $entryId,string $raw,array $weights):array
{
    $raw=strtoupper(trim($raw));
    $entry=$pdo->prepare("SELECT e.id,e.dance_role,j.scoring_scope FROM bdc_scoring_entries e JOIN bdc_scoring_judges j ON j.id=:judge WHERE e.id=:entry AND e.round_id=:round AND e.entry_status='active'");
    $entry->execute(['judge'=>$judgeId,'entry'=>$entryId,'round'=>$roundId]);$row=$entry->fetch();
    if(!$row)throw new RuntimeException('Competitor is not available for this round.');
    $role=(string)$row['dance_role'];
    if(!in_array((string)$row['scoring_scope'],['all',$role],true))throw new RuntimeException('This competitor is outside your assigned judging panel.');
    if($raw===''){$pdo->prepare('DELETE FROM bdc_scoring_marks WHERE round_id=:round AND entry_id=:entry AND judge_id=:judge')->execute(['round'=>$roundId,'entry'=>$entryId,'judge'=>$judgeId]);return heatsSelectionState($pdo,$roundId,$judgeId,$role);}
    $type='';$alt=null;$weight=0.0;
    if(in_array($raw,['1','Y','YES'],true)){$type='yes';$weight=(float)$weights['yes_weight'];}
    elseif(in_array($raw,['A1','2'],true)){$type='alt';$alt=1;$weight=(float)$weights['alt1_weight'];}
    elseif(in_array($raw,['A2','3'],true)){$type='alt';$alt=2;$weight=(float)$weights['alt2_weight'];}
    elseif(in_array($raw,['A3','4'],true)){$type='alt';$alt=3;$weight=(float)$weights['alt3_weight'];}
    else throw new RuntimeException('Choose YES, A1, A2 or A3.');

    if($type==='yes'){
        $limit=max(1,(int)($weights['yes_count']??10));
        $count=$pdo->prepare("SELECT COUNT(*) FROM bdc_scoring_marks m JOIN bdc_scoring_entries e ON e.id=m.entry_id WHERE m.round_id=:round AND m.judge_id=:judge AND e.dance_role=:role AND e.entry_status='active' AND m.mark_type='yes' AND m.entry_id<>:entry");
        $count->execute(['round'=>$roundId,'judge'=>$judgeId,'role'=>$role,'entry'=>$entryId]);
        if((int)$count->fetchColumn()>=$limit)throw new RuntimeException('Maximum '.$limit.' YES selections allowed for '.ucfirst($role).'s in this round. Clear or change another YES first.');
    }
    if($type==='alt'&&$alt!==null){
        $duplicate=$pdo->prepare("SELECT e.display_name FROM bdc_scoring_marks m JOIN bdc_scoring_entries e ON e.id=m.entry_id WHERE m.round_id=:round AND m.judge_id=:judge AND e.dance_role=:role AND e.entry_status='active' AND m.mark_type='alt' AND m.alt_rank=:alt AND m.entry_id<>:entry LIMIT 1");
        $duplicate->execute(['round'=>$roundId,'judge'=>$judgeId,'role'=>$role,'alt'=>$alt,'entry'=>$entryId]);
        if($name=$duplicate->fetchColumn())throw new RuntimeException('A'.$alt.' is already assigned to '.$name.' for '.ucfirst($role).'s. Each alternate rank can be used only once.');
    }

    $pdo->prepare("INSERT INTO bdc_scoring_marks(round_id,entry_id,judge_id,mark_type,alt_rank,weighted_score,updated_by) VALUES(:round,:entry,:judge,:type,:alt,:weight,NULL) ON DUPLICATE KEY UPDATE mark_type=VALUES(mark_type),alt_rank=VALUES(alt_rank),weighted_score=VALUES(weighted_score),updated_by=NULL,updated_at=NOW()")
        ->execute(['round'=>$roundId,'entry'=>$entryId,'judge'=>$judgeId,'type'=>$type,'alt'=>$alt,'weight'=>$weight]);
    return heatsSelectionState($pdo,$roundId,$judgeId,$role);
}

function saveFinalJudgeRank(PDO $pdo,int $roundId,int $judgeId,int $pairId,string $raw):void
{
    if($raw===''||!ctype_digit($raw))throw new RuntimeException('Enter a whole-number rank.');
    $countStmt=$pdo->prepare("SELECT COUNT(*) FROM bdc_scoring_final_pairs WHERE round_id=:round AND pairing_status='confirmed'");$countStmt->execute(['round'=>$roundId]);$count=(int)$countStmt->fetchColumn();
    $rank=(int)$raw;if($rank<1||$rank>$count)throw new RuntimeException('Ranks must be between 1 and '.$count.'.');
    $pair=$pdo->prepare("SELECT id FROM bdc_scoring_final_pairs WHERE id=:pair AND round_id=:round AND pairing_status='confirmed'");$pair->execute(['pair'=>$pairId,'round'=>$roundId]);if(!(int)$pair->fetchColumn())throw new RuntimeException('Final pair not found.');
    $duplicate=$pdo->prepare("SELECT pair_id FROM bdc_scoring_final_marks WHERE round_id=:round AND judge_id=:judge AND rank_value=:rank AND pair_id<>:pair LIMIT 1");$duplicate->execute(['round'=>$roundId,'judge'=>$judgeId,'rank'=>$rank,'pair'=>$pairId]);if($duplicate->fetchColumn())throw new RuntimeException('Rank '.$rank.' is already assigned to another couple.');
    $pdo->prepare("INSERT INTO bdc_scoring_final_marks(round_id,pair_id,judge_id,rank_value,updated_by) VALUES(:round,:pair,:judge,:rank,NULL) ON DUPLICATE KEY UPDATE rank_value=VALUES(rank_value),updated_by=NULL,updated_at=NOW()")
        ->execute(['round'=>$roundId,'pair'=>$pairId,'judge'=>$judgeId,'rank'=>$rank]);
}

function validateJudgeComplete(PDO $pdo,array $session,int $yesLimit):void
{
    $roundId=(int)$session['round_id'];$judgeId=(int)$session['judge_id'];
    if((string)$session['round_type']==='final'){
        $total=$pdo->prepare("SELECT COUNT(*) FROM bdc_scoring_final_pairs WHERE round_id=:round AND pairing_status='confirmed'");$total->execute(['round'=>$roundId]);$total=(int)$total->fetchColumn();
        if($total<1)throw new RuntimeException('Final pairing has not been confirmed yet.');
        $ranks=$pdo->prepare("SELECT rank_value FROM bdc_scoring_final_marks WHERE round_id=:round AND judge_id=:judge ORDER BY rank_value");$ranks->execute(['round'=>$roundId,'judge'=>$judgeId]);$values=array_map('intval',$ranks->fetchAll(PDO::FETCH_COLUMN));
        if(count($values)!==$total||$values!==range(1,$total))throw new RuntimeException('Rank every couple once from 1 to '.$total.' before submitting.');
        return;
    }
    $scope=(string)$session['scoring_scope'];
    foreach(['leader','follower'] as $role){
        if(!in_array($scope,['all',$role],true))continue;
        $totalStmt=$pdo->prepare("SELECT COUNT(*) FROM bdc_scoring_entries WHERE round_id=:round AND dance_role=:role AND entry_status='active'");$totalStmt->execute(['round'=>$roundId,'role'=>$role]);$total=(int)$totalStmt->fetchColumn();
        if($total<1)continue;
        $state=heatsSelectionState($pdo,$roundId,$judgeId,$role);
        $requiredYes=min($yesLimit,$total);
        if($state['yes']!==$requiredYes)throw new RuntimeException('Select exactly '.$requiredYes.' YES for '.ucfirst($role).'s before submitting. Currently selected: '.$state['yes'].'.');
        foreach(['A1','A2','A3'] as $alt){if($state[$alt]>1)throw new RuntimeException($alt.' can be used only once for '.ucfirst($role).'s.');}
    }
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        $fresh=AutomaticJudgeBrowserService::byToken($pdo,$token);if(!$fresh)throw new RuntimeException('Judge scoring link expired.');
        if((string)$fresh['status']==='submitted')throw new RuntimeException('Your scores have already been submitted and locked.');
        $action=(string)($_POST['action']??'save');
        if($action==='save_score'){
            if($isFinal){saveFinalJudgeRank($pdo,$roundId,$judgeId,(int)($_POST['pair_id']??0),trim((string)($_POST['value']??'')));$state=null;}
            else $state=saveHeatsJudgeMark($pdo,$roundId,$judgeId,(int)($_POST['entry_id']??0),trim((string)($_POST['value']??'')),$weights);
            AutomaticJudgeBrowserService::markSaved($pdo,$sessionId);
            if(isset($_POST['ajax']))judgeJson(['ok'=>true,'saved_at'=>date('H:i:s'),'selection_state'=>$state]);
            $notice='Draft saved.';
        }elseif($action==='submit'){
            validateJudgeComplete($pdo,$fresh,$yesLimit);AutomaticJudgeBrowserService::submit($pdo,$sessionId);$locked=true;$notice='Scores submitted and locked. Thank you.';
        }
    }catch(Throwable $e){if(isset($_POST['ajax']))judgeJson(['ok'=>false,'error'=>$e->getMessage()],422);$error=$e->getMessage();}
}

$initialState=['leader'=>['yes'=>0,'A1'=>0,'A2'=>0,'A3'=>0],'follower'=>['yes'=>0,'A1'=>0,'A2'=>0,'A3'=>0]];
if($isFinal){
    $stmt=$pdo->prepare("SELECT fp.id pair_id,fp.pair_number,le.display_name leader_name,le.bib_number leader_bib,fe.display_name follower_name,fe.bib_number follower_bib,fm.rank_value FROM bdc_scoring_final_pairs fp JOIN bdc_scoring_entries le ON le.id=fp.leader_entry_id JOIN bdc_scoring_entries fe ON fe.id=fp.follower_entry_id LEFT JOIN bdc_scoring_final_marks fm ON fm.round_id=fp.round_id AND fm.pair_id=fp.id AND fm.judge_id=:judge WHERE fp.round_id=:round AND fp.pairing_status='confirmed' ORDER BY fp.pair_number");
    $stmt->execute(['judge'=>$judgeId,'round'=>$roundId]);$pairs=$stmt->fetchAll();
}else{
    $scope=(string)$session['scoring_scope'];
    $stmt=$pdo->prepare("SELECT e.id,e.dance_role,e.bib_number,e.display_name,m.mark_type,m.alt_rank FROM bdc_scoring_entries e LEFT JOIN bdc_scoring_marks m ON m.round_id=e.round_id AND m.entry_id=e.id AND m.judge_id=:judge WHERE e.round_id=:round AND e.entry_status='active' AND (:scope='all' OR e.dance_role=:scope2) ORDER BY e.dance_role,e.bib_number");
    $stmt->execute(['judge'=>$judgeId,'round'=>$roundId,'scope'=>$scope,'scope2'=>$scope]);$allEntries=$stmt->fetchAll();$entries=['leader'=>[],'follower'=>[]];foreach($allEntries as $entry){$entry['current_mark']=$entry['mark_type']==='yes'?'YES':($entry['mark_type']==='alt'?'A'.(int)$entry['alt_rank']:'');$entries[$entry['dance_role']][]=$entry;}
    foreach(['leader','follower'] as $role)if($entries[$role])$initialState[$role]=heatsSelectionState($pdo,$roundId,$judgeId,$role);
}
$category=ucwords(str_replace('_',' ',(string)$session['division']));
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Judge Scoring | BDC</title><style>
*{box-sizing:border-box}body{margin:0;background:#f4f6f9;color:#172033;font-family:Arial,sans-serif}.top{background:linear-gradient(110deg,#111,#42101c);color:#fff;padding:22px 18px;border-bottom:5px solid #d10f32}.wrap{max-width:760px;margin:auto;padding:18px}.eyebrow{font-size:12px;font-weight:800;letter-spacing:.12em;color:#ffb7c3}.top h1{margin:6px 0 4px;font-size:26px}.meta{opacity:.9;line-height:1.5}.chief{display:inline-block;background:#ffd966;color:#111;padding:4px 9px;border-radius:999px;font-size:12px;font-weight:800;margin-top:8px}.alert{padding:12px 14px;border-radius:10px;margin:0 0 14px}.error{background:#ffe6e6;color:#8a1010}.success{background:#e4f6ea;color:#176b36}.warning{background:#fff3cd;color:#664d03}.tabs{display:flex;gap:8px;position:sticky;top:0;background:#f4f6f9;padding:8px 0;z-index:3}.tab{border:1px solid #ccd2da;background:#fff;padding:10px 14px;border-radius:9px;font-weight:700;cursor:pointer}.tab.active{background:#172033;color:#fff}.panel{display:none}.panel.active{display:block}.counter{position:sticky;top:58px;z-index:2;background:#fff;border:1px solid #dfe3e8;border-radius:10px;padding:10px 12px;margin-bottom:10px;font-weight:700}.counter.over{background:#ffe6e6;color:#8a1010}.card{background:#fff;border:1px solid #dfe3e8;border-radius:14px;padding:15px;margin-bottom:10px;box-shadow:0 3px 10px rgba(15,23,42,.04)}.bib{font-size:12px;font-weight:800;color:#667085}.name{font-size:18px;font-weight:800;margin:3px 0 12px}.choices{display:grid;grid-template-columns:1.25fr repeat(3,1fr) 1.25fr;gap:12px}.choice{border:1px solid #b8c0cc;background:#fff;border-radius:9px;padding:13px 8px;min-height:48px;font-weight:800;cursor:pointer}.choice[data-value="YES"]{border:2px solid #198754}.choice.clear{border:2px solid #dc3545}.choice.active{background:#172033;color:#fff;border-color:#172033}.choice.clear{color:#8a1010}.saved{font-size:12px;color:#18864b;margin-top:7px;min-height:15px}.saved.fail{color:#b42318;font-weight:700}.score{width:120px;font-size:22px;font-weight:800;text-align:center;padding:10px;border:2px solid #cdd3dc;border-radius:10px}.submitbar{position:sticky;bottom:0;background:rgba(244,246,249,.96);padding:14px 0}.submit{width:100%;border:0;background:#d10f32;color:#fff;padding:15px;border-radius:11px;font-size:17px;font-weight:800}.locked{background:#e8f6ed;border:1px solid #a9d8b9;border-radius:14px;padding:24px;text-align:center;font-weight:800}.small{font-size:12px;color:#667085;margin-top:8px}@media(max-width:520px){.choices{grid-template-columns:repeat(2,1fr);gap:12px}.choices .clear{grid-column:span 2}}
</style></head><body><header class="top"><div class="wrap" style="padding:0"><div class="eyebrow">BDC AUTOMATIC SCORING</div><h1><?=e((string)$session['event_name'])?></h1><div class="meta"><?=e($category)?> · <?=e(strtoupper((string)$session['round_type']))?><br>Judge: <strong><?=e((string)$session['judge_name'])?></strong></div><?php if((int)$session['is_chief']===1):?><span class="chief">★ CHIEF JUDGE</span><?php endif;?></div></header><main class="wrap">
<?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?><?php if($notice):?><div class="alert success"><?=e($notice)?></div><?php endif;?>
<?php if($locked):?><div class="locked">✓ SCORES SUBMITTED<br><div class="small">Your scoring is locked. Please contact the organiser if a correction is required.</div></div>
<?php elseif($isFinal):?>
<p>Rank every confirmed couple once. Use each rank from <strong>1 to <?=count($pairs)?></strong> exactly once.</p>
<?php foreach($pairs as $pair):?><div class="card"><div class="bib">PAIR #<?=(int)$pair['pair_number']?></div><div class="name"><?=e($pair['leader_name'])?> &amp; <?=e($pair['follower_name'])?></div><div><label>Rank</label><input class="score judge-input" inputmode="numeric" type="number" min="1" max="<?=count($pairs)?>" data-pair="<?=(int)$pair['pair_id']?>" value="<?=e((string)($pair['rank_value']??''))?>"><span class="saved"></span></div></div><?php endforeach;?>
<form method="post" class="submitbar" onsubmit="return confirm('Submit and lock your Final rankings? You will not be able to change them after submission.')"><input type="hidden" name="token" value="<?=e($token)?>"><input type="hidden" name="action" value="submit"><button class="submit">SUBMIT FINAL RANKING</button></form>
<?php else:?>
<div class="alert success"><strong>Selection rules:</strong> maximum <?=e((string)$yesLimit)?> YES per role. A1, A2 and A3 can each be used only once per role. Everyone else may remain blank.</div>
<div id="ruleMessage" class="alert warning" style="display:none"></div>
<div class="tabs"><?php foreach(['leader'=>'LEADERS','follower'=>'FOLLOWERS'] as $role=>$label):if(!$entries[$role])continue;?><button class="tab <?=$role==='leader'?'active':''?>" data-tab="<?=$role?>"><?=$label?></button><?php endforeach;?></div>
<?php $first=true;foreach(['leader','follower'] as $role):if(!$entries[$role])continue;?><section class="panel <?=$first?'active':''?>" data-panel="<?=$role?>"><div class="counter" data-counter="<?=$role?>"></div><?php $first=false;foreach($entries[$role] as $entry):?><div class="card" data-entry-card="<?=(int)$entry['id']?>" data-role="<?=$role?>"><div class="bib"><?=strtoupper($role)==='LEADER'?'LEAD':'FOLLOW'?> #<?=(int)$entry['bib_number']?></div><div class="name"><?=e($entry['display_name'])?></div><div class="choices"><?php foreach(['YES','A1','A2','A3'] as $choice):?><button type="button" class="choice <?=$entry['current_mark']===$choice?'active':''?>" data-value="<?=$choice?>" data-entry="<?=(int)$entry['id']?>"><?=$choice?></button><?php endforeach;?><button type="button" class="choice clear" data-value="" data-entry="<?=(int)$entry['id']?>">Clear</button></div><div class="saved"></div></div><?php endforeach;?></section><?php endforeach;?>
<form method="post" class="submitbar" onsubmit="return confirm('Submit and lock all your marks? You will not be able to change them after submission.')"><input type="hidden" name="token" value="<?=e($token)?>"><input type="hidden" name="action" value="submit"><button class="submit">SUBMIT SCORES &amp; LOCK</button></form>
<?php endif;?></main>
<?php if(!$locked):?><script>
const token=<?=json_encode($token)?>,yesLimit=<?=$yesLimit?>,selectionState=<?=json_encode($initialState,JSON_UNESCAPED_SLASHES)?>;
function showRuleMessage(message){const box=document.getElementById('ruleMessage');if(!box)return;box.textContent=message;box.style.display='block';clearTimeout(showRuleMessage.timer);showRuleMessage.timer=setTimeout(()=>box.style.display='none',4500)}
function updateCounter(role){const state=selectionState[role]||{yes:0,A1:0,A2:0,A3:0},el=document.querySelector('[data-counter="'+role+'"]');if(!el)return;el.textContent='YES '+state.yes+' / '+yesLimit+'   •   A1 '+state.A1+' / 1   •   A2 '+state.A2+' / 1   •   A3 '+state.A3+' / 1';el.classList.toggle('over',state.yes>yesLimit||state.A1>1||state.A2>1||state.A3>1)}
function localRuleCheck(button){const card=button.closest('[data-entry-card]');if(!card)return true;const role=card.dataset.role,state=selectionState[role]||{yes:0,A1:0,A2:0,A3:0},current=card.querySelector('.choice.active')?.dataset.value||'',next=button.dataset.value;if(next==='YES'&&current!=='YES'&&state.yes>=yesLimit){showRuleMessage('Maximum '+yesLimit+' YES selections allowed for '+(role==='leader'?'Leaders':'Followers')+'. Clear or change another YES first.');return false;}if(['A1','A2','A3'].includes(next)&&current!==next&&state[next]>=1){showRuleMessage(next+' is already used for '+(role==='leader'?'Leaders':'Followers')+'. Each alternate rank can be used only once.');return false;}return true}
function adjustLocalState(card,newValue){if(!card)return;const role=card.dataset.role,state=selectionState[role],old=card.querySelector('.choice.active')?.dataset.value||'';if(old==='YES')state.yes=Math.max(0,state.yes-1);else if(['A1','A2','A3'].includes(old))state[old]=Math.max(0,state[old]-1);if(newValue==='YES')state.yes++;else if(['A1','A2','A3'].includes(newValue))state[newValue]++;updateCounter(role)}
async function saveChoice(button){const card=button.closest?button.closest('[data-entry-card]'):null;if(card&&!localRuleCheck(button))return;const saved=card?card.querySelector('.saved'):button.parentElement.querySelector('.saved');if(saved){saved.textContent='Saving…';saved.classList.remove('fail')}const body=new URLSearchParams({token:token,action:'save_score',ajax:'1',value:button.dataset.value});if(button.dataset.entry)body.set('entry_id',button.dataset.entry);if(button.dataset.pair)body.set('pair_id',button.dataset.pair);try{const r=await fetch(location.href,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body.toString()});const data=await r.json();if(!r.ok||!data.ok)throw new Error(data.error||'Save failed');if(card){adjustLocalState(card,button.dataset.value);card.querySelectorAll('.choice').forEach(x=>x.classList.remove('active'));if(button.dataset.value)button.classList.add('active');const role=card.dataset.role;if(data.selection_state){selectionState[role]=data.selection_state;updateCounter(role)}}if(saved)saved.textContent='Saved '+data.saved_at;}catch(e){if(saved){saved.textContent=e.message;saved.classList.add('fail')}showRuleMessage(e.message)}}
document.querySelectorAll('.choice').forEach(button=>button.addEventListener('click',()=>saveChoice(button)));
document.querySelectorAll('.judge-input').forEach(input=>input.addEventListener('change',()=>{const fake={dataset:{value:input.value,pair:input.dataset.pair},parentElement:input.parentElement,closest:()=>null};saveChoice(fake)}));
document.querySelectorAll('.tab').forEach(button=>button.addEventListener('click',()=>{document.querySelectorAll('.tab').forEach(x=>x.classList.toggle('active',x===button));document.querySelectorAll('.panel').forEach(panel=>panel.classList.toggle('active',panel.dataset.panel===button.dataset.tab));}));
updateCounter('leader');updateCounter('follower');
</script><?php endif;?></body></html>
