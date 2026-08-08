<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\AutomaticJudgeBrowserService;

Auth::requireAdmin();
$pdo=Database::connection();
$roundId=(int)($_GET['round_id']??$_POST['round_id']??0);
$userId=(int)(Auth::user()['id']??0);

$roundStmt=$pdo->prepare("SELECT r.*,e.name event_name FROM bdc_scoring_rounds r JOIN bdc_events e ON e.id=r.event_id WHERE r.id=:round LIMIT 1");
$roundStmt->execute(['round'=>$roundId]);
$round=$roundStmt->fetch();
if(!$round||($round['scoring_mode']??'')!=='automated'){http_response_code(404);exit('Automatic scoring round not found.');}

$pdo->exec("CREATE TABLE IF NOT EXISTS bdc_scoring_round_setup (
    round_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    confirmed_at DATETIME NULL,
    confirmed_by BIGINT UNSIGNED NULL,
    confirmed_snapshot_hash CHAR(64) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_bdc_scoring_round_setup_round_runtime FOREIGN KEY (round_id) REFERENCES bdc_scoring_rounds(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

function setupState(PDO $pdo,int $roundId):array{
    $stmt=$pdo->prepare('SELECT * FROM bdc_scoring_round_setup WHERE round_id=:round LIMIT 1');
    $stmt->execute(['round'=>$roundId]);
    return $stmt->fetch()?:['round_id'=>$roundId,'confirmed_at'=>null,'confirmed_by'=>null,'confirmed_snapshot_hash'=>null];
}
function setupJudges(PDO $pdo,int $roundId):array{
    $stmt=$pdo->prepare('SELECT id,judge_name,judge_order,is_chief,scoring_scope FROM bdc_scoring_judges WHERE round_id=:round ORDER BY judge_order,id');
    $stmt->execute(['round'=>$roundId]);
    return $stmt->fetchAll();
}
function setupEntries(PDO $pdo,int $roundId):array{
    $stmt=$pdo->prepare("SELECT id,dance_role,bib_number,display_name FROM bdc_scoring_entries WHERE round_id=:round AND entry_status='active' ORDER BY dance_role,bib_number,display_name");
    $stmt->execute(['round'=>$roundId]);
    return $stmt->fetchAll();
}
function setupSaveJudges(PDO $pdo,int $roundId,array $rows,string $chiefKey):void{
    $state=setupState($pdo,$roundId);
    if(!empty($state['confirmed_at']))throw new RuntimeException('Setup is already confirmed. Judge changes are locked.');
    $clean=[];
    foreach($rows as $key=>$row){
        if(!is_array($row))continue;
        $name=trim((string)($row['name']??''));
        if($name==='')continue;
        $scope=(string)($row['scope']??'all');
        if(!in_array($scope,['all','leader','follower'],true))$scope='all';
        $id=(int)($row['id']??0);
        $clean[(string)$key]=['id'=>$id,'name'=>$name,'scope'=>$scope];
    }
    $names=array_map(static fn($r)=>mb_strtolower($r['name']),array_values($clean));
    if(count($names)!==count(array_unique($names)))throw new RuntimeException('Judge names must be unique.');

    $existing=setupJudges($pdo,$roundId);
    $existingIds=array_map('intval',array_column($existing,'id'));
    $pdo->beginTransaction();
    try{
        $kept=[];$order=1;$chiefId=null;
        $update=$pdo->prepare("UPDATE bdc_scoring_judges SET judge_name=:name,judge_order=:ord,is_chief=:chief,scoring_scope=:scope WHERE id=:id AND round_id=:round");
        $insert=$pdo->prepare("INSERT INTO bdc_scoring_judges(round_id,judge_name,judge_order,is_chief,scoring_scope) VALUES(:round,:name,:ord,:chief,:scope)");
        foreach($clean as $key=>$row){
            $isChief=$key===$chiefKey?1:0;
            if($row['id']>0&&in_array($row['id'],$existingIds,true)){
                $id=$row['id'];
                $update->execute(['name'=>$row['name'],'ord'=>$order,'chief'=>$isChief,'scope'=>$row['scope'],'id'=>$id,'round'=>$roundId]);
            }else{
                $insert->execute(['round'=>$roundId,'name'=>$row['name'],'ord'=>$order,'chief'=>$isChief,'scope'=>$row['scope']]);
                $id=(int)$pdo->lastInsertId();
            }
            $kept[]=$id;if($isChief)$chiefId=$id;$order++;
        }
        $remove=array_values(array_diff($existingIds,$kept));
        if($remove){
            $ph=implode(',',array_fill(0,count($remove),'?'));
            $pdo->prepare("DELETE FROM bdc_scoring_judge_sessions WHERE judge_id IN ($ph)")->execute($remove);
            $pdo->prepare("DELETE FROM bdc_scoring_marks WHERE judge_id IN ($ph)")->execute($remove);
            $pdo->prepare("DELETE FROM bdc_scoring_final_marks WHERE judge_id IN ($ph)")->execute($remove);
            $pdo->prepare("DELETE FROM bdc_scoring_judges WHERE id IN ($ph)")->execute($remove);
        }
        $pdo->prepare('UPDATE bdc_scoring_rounds SET chief_judge_id=:chief WHERE id=:round')->execute(['chief'=>$chiefId,'round'=>$roundId]);
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function setupValidate(PDO $pdo,int $roundId):array{
    $judges=setupJudges($pdo,$roundId);$entries=setupEntries($pdo,$roundId);
    if(count($judges)<3)throw new RuntimeException('Add at least 3 judges before confirmation.');
    if(count(array_filter($judges,static fn($j)=>(int)$j['is_chief']===1))!==1)throw new RuntimeException('Select exactly one Chief Judge.');
    foreach(['leader','follower'] as $role){
        $panel=count(array_filter($judges,static fn($j)=>in_array((string)$j['scoring_scope'],['all',$role],true)));
        if($panel<3)throw new RuntimeException(ucfirst($role).' panel must have at least 3 judges.');
        $roleEntries=array_values(array_filter($entries,static fn($e)=>(string)$e['dance_role']===$role));
        if(!$roleEntries)throw new RuntimeException('Add at least one '.ucfirst($role).' through the Registration Desk before confirmation.');
        $bibs=[];
        foreach($roleEntries as $entry){
            $bib=(int)$entry['bib_number'];
            if($bib<1)throw new RuntimeException($entry['display_name'].' is missing a valid bib number.');
            if(isset($bibs[$bib]))throw new RuntimeException('Duplicate '.ucfirst($role).' bib '.$bib.' must be corrected before confirmation.');
            $bibs[$bib]=true;
        }
    }
    return [$judges,$entries];
}

$isJson=isset($_GET['ajax'])||str_contains((string)($_SERVER['HTTP_ACCEPT']??''),'application/json');
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Invalid security token.');
        $action=(string)($_POST['action']??'save_draft');
        if($action==='save_draft'){
            $rows=$_POST['judges']??[];$chief=(string)($_POST['chief_key']??'');
            setupSaveJudges($pdo,$roundId,is_array($rows)?$rows:[],$chief);
            $message='Draft saved.';
        }elseif($action==='confirm_setup'){
            $rows=$_POST['judges']??[];$chief=(string)($_POST['chief_key']??'');
            setupSaveJudges($pdo,$roundId,is_array($rows)?$rows:[],$chief);
            [$judges,$entries]=setupValidate($pdo,$roundId);
            $snapshot=hash('sha256',json_encode(['judges'=>$judges,'entries'=>$entries],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
            $pdo->prepare("INSERT INTO bdc_scoring_round_setup(round_id,confirmed_at,confirmed_by,confirmed_snapshot_hash) VALUES(:round,NOW(),:user,:hash)
                ON DUPLICATE KEY UPDATE confirmed_at=NOW(),confirmed_by=VALUES(confirmed_by),confirmed_snapshot_hash=VALUES(confirmed_snapshot_hash)")
                ->execute(['round'=>$roundId,'user'=>$userId?:null,'hash'=>$snapshot]);
            $audit=$pdo->prepare('INSERT INTO bdc_scoring_audit(round_id,user_id,action,details_json) VALUES(:round,:user,:action,:details)');
            $audit->execute(['round'=>$roundId,'user'=>$userId?:null,'action'=>'automatic_setup_confirmed','details'=>json_encode(['judges'=>count($judges),'competitors'=>count($entries),'snapshot_hash'=>$snapshot],JSON_UNESCAPED_SLASHES)]);
            $message='Judges and competitors confirmed. Judge links are now available.';
        }else{throw new RuntimeException('Invalid setup action.');}
        if($isJson){header('Content-Type: application/json');echo json_encode(['ok'=>true,'message'=>$message]);exit;}
    }catch(Throwable $e){if($isJson){http_response_code(422);header('Content-Type: application/json');echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);exit;}$error=$e->getMessage();}
}

$state=setupState($pdo,$roundId);$judges=setupJudges($pdo,$roundId);$entries=setupEntries($pdo,$roundId);$csrf=Csrf::token();
$locked=!empty($state['confirmed_at']);
$leaders=array_values(array_filter($entries,static fn($e)=>(string)$e['dance_role']==='leader'));
$followers=array_values(array_filter($entries,static fn($e)=>(string)$e['dance_role']==='follower'));
if(!$judges&&!$locked){$judges=[['id'=>0,'judge_name'=>'','judge_order'=>1,'is_chief'=>0,'scoring_scope'=>'all'],['id'=>0,'judge_name'=>'','judge_order'=>2,'is_chief'=>0,'scoring_scope'=>'all'],['id'=>0,'judge_name'=>'','judge_order'=>3,'is_chief'=>0,'scoring_scope'=>'all']];}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>
*{box-sizing:border-box}body{font-family:Arial,sans-serif;margin:0;color:#172033;background:#fff}.wrap{padding:2px}.section{border:1px solid #dde2e8;border-radius:12px;padding:14px;margin-bottom:12px}.head{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:10px}.head h3{margin:0;font-size:17px}.small{font-size:12px;color:#667085}.judge-row{display:grid;grid-template-columns:2fr 1fr 90px 70px;gap:8px;align-items:center;margin-bottom:8px}.judge-row input,.judge-row select{width:100%;padding:9px;border:1px solid #ccd3dc;border-radius:8px}.btn{border:1px solid #bcc5d0;background:#fff;padding:8px 11px;border-radius:8px;font-weight:700;cursor:pointer}.btn.primary{background:#1769ff;border-color:#1769ff;color:#fff}.btn.dark{background:#172033;border-color:#172033;color:#fff}.btn.danger{color:#b42318;border-color:#efb1aa}.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}.status{padding:8px 10px;border-radius:8px;background:#eef4ff;color:#174ea6;font-size:13px}.ok{background:#e8f6ed;color:#176b36}.bad{background:#ffe8e8;color:#8a1515}.competitors{display:grid;grid-template-columns:1fr 1fr;gap:12px}.list{border:1px solid #e2e6eb;border-radius:9px;overflow:hidden}.item{display:flex;justify-content:space-between;padding:8px 10px;border-top:1px solid #edf0f3;font-size:13px}.item:first-child{border-top:0}.empty{padding:12px;color:#667085;font-size:13px}.locked{opacity:.75}.saved{font-size:12px;color:#176b36;min-width:90px;text-align:right}@media(max-width:700px){.judge-row{grid-template-columns:1fr}.competitors{grid-template-columns:1fr}.saved{text-align:left}}
</style></head><body><div class="wrap">
<div class="head"><div><h3>Automatic Scoring Setup</h3><div class="small"><?=e($round['event_name'])?> · <?=e(ucwords(str_replace('_',' ',(string)$round['division'])))?> · <?=e(ucfirst((string)$round['round_type']))?></div></div><div id="saveState" class="saved"><?= $locked?'Confirmed':'Draft autosave on' ?></div></div>
<?php if(!empty($message)):?><div class="status ok"><?=e($message)?></div><?php endif;?><?php if(!empty($error)):?><div class="status bad"><?=e($error)?></div><?php endif;?>
<form id="setupForm" method="post" class="<?=$locked?'locked':''?>"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="action" id="setupAction" value="save_draft"><input type="hidden" name="chief_key" id="chiefKey" value="">
<div class="section"><div class="head"><div><h3>1. Judges</h3><div class="small">Minimum 3. Add as many judges as required. Changes autosave.</div></div><?php if(!$locked):?><button type="button" class="btn" id="addJudge">+ Add Judge</button><?php endif;?></div><div id="judgeRows">
<?php foreach($judges as $i=>$j):$key='j'.$i;?><div class="judge-row" data-key="<?=$key?>"><input type="hidden" name="judges[<?=$key?>][id]" value="<?=(int)$j['id']?>"><input <?= $locked?'disabled':'' ?> name="judges[<?=$key?>][name]" value="<?=e((string)$j['judge_name'])?>" placeholder="Judge name"><select <?= $locked?'disabled':'' ?> name="judges[<?=$key?>][scope]"><option value="all" <?=($j['scoring_scope']??'all')==='all'?'selected':''?>>All</option><option value="leader" <?=($j['scoring_scope']??'')==='leader'?'selected':''?>>Leaders</option><option value="follower" <?=($j['scoring_scope']??'')==='follower'?'selected':''?>>Followers</option></select><label><input type="radio" class="chief-radio" name="chief_pick" value="<?=$key?>" <?= (int)$j['is_chief']===1?'checked':'' ?> <?= $locked?'disabled':'' ?>> Chief</label><?php if(!$locked):?><button type="button" class="btn danger removeJudge">Remove</button><?php endif;?></div><?php endforeach;?></div></div>
<div class="section"><div class="head"><div><h3>2. Competitors</h3><div class="small">Competitors and bibs come from the Registration Desk and update here when this page reloads.</div></div><button type="button" class="btn" onclick="location.reload()">Refresh Competitors</button></div><div class="competitors"><div><strong>Leaders · <?=count($leaders)?></strong><div class="list"><?php if(!$leaders):?><div class="empty">No Leaders added yet.</div><?php else:foreach($leaders as $e):?><div class="item"><span>#<?=(int)$e['bib_number']?> · <?=e($e['display_name'])?></span><span>Ready</span></div><?php endforeach;endif;?></div></div><div><strong>Followers · <?=count($followers)?></strong><div class="list"><?php if(!$followers):?><div class="empty">No Followers added yet.</div><?php else:foreach($followers as $e):?><div class="item"><span>#<?=(int)$e['bib_number']?> · <?=e($e['display_name'])?></span><span>Ready</span></div><?php endforeach;endif;?></div></div></div></div>
<?php if(!$locked):?><div class="actions"><button type="submit" class="btn dark" onclick="document.getElementById('setupAction').value='save_draft'">Save Draft</button><button type="submit" class="btn primary" onclick="document.getElementById('setupAction').value='confirm_setup'">Confirm Judges &amp; Competitors</button></div><?php else:?><div class="status ok">✓ Judges and competitors confirmed. Judge browser links are enabled below.</div><?php endif;?></form></div>
<?php if(!$locked):?><script>
const form=document.getElementById('setupForm'),rows=document.getElementById('judgeRows'),state=document.getElementById('saveState'),chiefKey=document.getElementById('chiefKey');let n=<?=(int)count($judges)?>,timer=null,saving=false;
function syncChief(){const c=document.querySelector('.chief-radio:checked');chiefKey.value=c?c.value:'';}
function autosave(){if(saving)return;clearTimeout(timer);state.textContent='Saving…';timer=setTimeout(async()=>{syncChief();saving=true;const fd=new FormData(form);fd.set('action','save_draft');try{const r=await fetch('?round_id=<?=$roundId?>&ajax=1',{method:'POST',body:fd,headers:{'Accept':'application/json'}});const j=await r.json();state.textContent=j.ok?'Saved':'Save failed';}catch(e){state.textContent='Save failed';}finally{saving=false;}},550);}
form.addEventListener('input',e=>{if(e.target.matches('input,select'))autosave();});form.addEventListener('change',e=>{if(e.target.matches('input,select'))autosave();});
document.getElementById('addJudge').addEventListener('click',()=>{const key='j'+n++;const div=document.createElement('div');div.className='judge-row';div.dataset.key=key;div.innerHTML='<input type="hidden" name="judges['+key+'][id]" value="0"><input name="judges['+key+'][name]" placeholder="Judge name"><select name="judges['+key+'][scope]"><option value="all">All</option><option value="leader">Leaders</option><option value="follower">Followers</option></select><label><input type="radio" class="chief-radio" name="chief_pick" value="'+key+'"> Chief</label><button type="button" class="btn danger removeJudge">Remove</button>';rows.appendChild(div);});
rows.addEventListener('click',e=>{if(e.target.classList.contains('removeJudge')){e.target.closest('.judge-row').remove();autosave();}});
form.addEventListener('submit',e=>{syncChief();if(document.getElementById('setupAction').value==='confirm_setup'&&!confirm('Confirm the current judges and competitors? Judge links will be generated and this setup will be locked.'))e.preventDefault();});
syncChief();
</script><?php endif;?></body></html>
