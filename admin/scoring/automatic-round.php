<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-transform, no-store, private');

require dirname(__DIR__,2).'/bootstrap.php';
require_once __DIR__.'/automatic-common-setup.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\AutomaticJudgeBrowserService;
use App\Services\DivisionProgressionService;
use App\Services\SpecialCategoryService;

Auth::requireAdmin();
$pdo=Database::connection();
$userId=(int)(Auth::user()['id']??0);
$roundId=(int)($_GET['round_id']??$_POST['round_id']??0);
$error='';$notice='';

function autoRound(PDO $pdo,int $roundId):array
{
    $stmt=$pdo->prepare("SELECT r.*,e.name event_name,e.event_date FROM bdc_scoring_rounds r JOIN bdc_events e ON e.id=r.event_id WHERE r.id=:id LIMIT 1");
    $stmt->execute(['id'=>$roundId]);
    $round=$stmt->fetch();
    if(!$round)throw new RuntimeException('Scoring round not found.');
    if(($round['scoring_mode']??'manual')!=='automated')throw new RuntimeException('This round is not Automatic scoring.');
    if(($round['round_type']??'')==='final')throw new RuntimeException('Automatic Final uses the Relative Placement Final workflow.');
    return $round;
}

function autoAudit(PDO $pdo,int $roundId,int $userId,string $action,array $details=[]):void
{
    $stmt=$pdo->prepare('INSERT INTO bdc_scoring_audit(round_id,user_id,action,details_json) VALUES(:round,:user,:action,:details)');
    $stmt->execute(['round'=>$roundId,'user'=>$userId?:null,'action'=>$action,'details'=>json_encode($details,JSON_UNESCAPED_UNICODE)]);
}

try{
    $round=autoRound($pdo,$roundId);
    if($_SERVER['REQUEST_METHOD']==='POST'){
        if(!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Your session expired. Refresh the page and try again.');
        $action=(string)($_POST['action']??'');

        if($action==='settings'){
            if(SpecialCategoryService::isSpecial((string)$round['division']))throw new RuntimeException('Special categories use fixed points and do not use participant-count tiers.');
            $tier=(int)($_POST['competition_tier']??2);$tierYes=[1=>5,2=>10,3=>15];
            if(!isset($tierYes[$tier]))throw new RuntimeException('Select a valid competition tier.');
            $yes=$tierYes[$tier];
            $pdo->prepare('UPDATE bdc_scoring_rounds SET yes_count=:yes,callback_count=:callback,tier_manual_override=1,yes_weight=10.00,alt1_weight=4.50,alt2_weight=4.30,alt3_weight=4.20 WHERE id=:id')
                ->execute(['yes'=>$yes,'callback'=>$yes,'id'=>$roundId]);
            autoAudit($pdo,$roundId,$userId,'heats_settings_saved',['tier'=>$tier,'yes_count'=>$yes,'automatic'=>true]);
            $notice='Tier settings saved.';

        }elseif($action==='save_judges'){
            $names=$_POST['judge_name']??[];$scopes=$_POST['judge_scope']??[];$chief=(int)($_POST['chief_index']??-1);$rows=[];
            foreach($names as $index=>$raw){$name=trim((string)$raw);if($name==='')continue;$scope=(string)($scopes[$index]??'all');if(!in_array($scope,['all','leader','follower'],true))$scope='all';$rows[]=['name'=>$name,'scope'=>$scope,'original'=>(int)$index];}
            if(count($rows)<3)throw new RuntimeException('Minimum 3 judges required.');
            if(count($rows)!==count(array_unique(array_map(static fn($row)=>mb_strtolower($row['name']),$rows))))throw new RuntimeException('Judge names must be unique.');
            $chiefRow=null;foreach($rows as $i=>$row)if($row['original']===$chief){$chiefRow=$i;break;}
            if($chiefRow===null)throw new RuntimeException('Select one Chief Judge.');
            foreach(['leader','follower'] as $role){$count=count(array_filter($rows,static fn($row)=>in_array($row['scope'],['all',$role],true)));if($count<3)throw new RuntimeException(ucfirst($role).' panel must have at least 3 judges.');}

            $existingStmt=$pdo->prepare('SELECT * FROM bdc_scoring_judges WHERE round_id=:round ORDER BY judge_order');$existingStmt->execute(['round'=>$roundId]);$existing=$existingStmt->fetchAll();$byName=[];foreach($existing as $judge)$byName[mb_strtolower(trim((string)$judge['judge_name']))]=$judge;
            $pdo->beginTransaction();
            try{
                $used=[];$chiefId=0;
                $update=$pdo->prepare('UPDATE bdc_scoring_judges SET judge_name=:name,judge_order=:ord,is_chief=:chief,scoring_scope=:scope WHERE id=:id AND round_id=:round');
                $insert=$pdo->prepare('INSERT INTO bdc_scoring_judges(round_id,judge_name,judge_order,is_chief,scoring_scope) VALUES(:round,:name,:ord,:chief,:scope)');
                foreach($rows as $i=>$row){$key=mb_strtolower($row['name']);$isChief=$i===$chiefRow?1:0;if(isset($byName[$key])){$id=(int)$byName[$key]['id'];$update->execute(['name'=>$row['name'],'ord'=>$i+1,'chief'=>$isChief,'scope'=>$row['scope'],'id'=>$id,'round'=>$roundId]);}else{$insert->execute(['round'=>$roundId,'name'=>$row['name'],'ord'=>$i+1,'chief'=>$isChief,'scope'=>$row['scope']]);$id=(int)$pdo->lastInsertId();}$used[]=$id;if($isChief)$chiefId=$id;}
                if($used){$placeholders=implode(',',array_fill(0,count($used),'?'));$params=array_merge([$roundId],$used);$pdo->prepare("DELETE FROM bdc_scoring_judges WHERE round_id=? AND id NOT IN ($placeholders)")->execute($params);}
                $pdo->prepare('UPDATE bdc_scoring_rounds SET chief_judge_id=:chief WHERE id=:round')->execute(['chief'=>$chiefId,'round'=>$roundId]);
                autoAudit($pdo,$roundId,$userId,'judges_saved',['count'=>count($rows),'automatic'=>true]);
                $pdo->commit();
            }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
            $notice='Judges saved. Secure judge links are available below when competitors are added.';

        }elseif($action==='add_entry'){
            $role=(string)($_POST['dance_role']??'');$bib=(int)($_POST['bib_number']??0);$term=trim((string)($_POST['competitor_search']??''));$entryMode=(string)($_POST['entry_mode']??'existing');
            if(!in_array($role,['leader','follower'],true)||$bib<1||$term==='')throw new RuntimeException('Choose role, bib and competitor name.');
            $dup=$pdo->prepare("SELECT display_name FROM bdc_scoring_entries WHERE round_id=:round AND dance_role=:role AND bib_number=:bib AND entry_status='active' LIMIT 1");$dup->execute(['round'=>$roundId,'role'=>$role,'bib'=>$bib]);if($name=$dup->fetchColumn())throw new RuntimeException('Bib '.$bib.' is already assigned to '.$name.'.');

            $selected='';if(preg_match('/^(BDC-\d+)/i',$term,$m))$selected=strtoupper($m[1]);$comp=null;
            if($entryMode!=='create'){
                $stmt=$pdo->prepare("SELECT * FROM bdc_competitors WHERE (bdc_id=:bdc OR LOWER(exact_name)=LOWER(:exact)) AND dance_role IN(:role,'both') AND status<>'archived' ORDER BY CASE WHEN dance_role=:preferred THEN 0 ELSE 1 END,id LIMIT 1");
                $stmt->execute(['bdc'=>$selected!==''?$selected:$term,'exact'=>$term,'role'=>$role,'preferred'=>$role]);$comp=$stmt->fetch()?:null;
                if(!$comp){$stmt=$pdo->prepare("SELECT * FROM bdc_competitors WHERE exact_name LIKE :like AND dance_role IN(:role,'both') AND status<>'archived' ORDER BY exact_name,id LIMIT 2");$stmt->execute(['like'=>'%'.$term.'%','role'=>$role]);$matches=$stmt->fetchAll();if(count($matches)===1)$comp=$matches[0];elseif(count($matches)>1)throw new RuntimeException('Several competitors match this name. Select the correct BDC ID.');}
                if(!$comp)throw new RuntimeException('Competitor not found in the BDC database. Use Create Name & Add only for a genuinely new BDC competitor.');
            }else{
                $normalised=strtolower(trim((string)preg_replace('/\s+/',' ',$term)));$same=$pdo->prepare('SELECT bdc_id,exact_name FROM bdc_competitors WHERE normalised_name=:name LIMIT 1');$same->execute(['name'=>$normalised]);if($existing=$same->fetch())throw new RuntimeException('A competitor with this name already exists: '.$existing['exact_name'].' ('.$existing['bdc_id'].').');
                $next=(int)$pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(bdc_id,5) AS UNSIGNED)),0)+1 FROM bdc_competitors WHERE bdc_id LIKE 'BDC-%'")->fetchColumn();$bdc='BDC-'.str_pad((string)$next,6,'0',STR_PAD_LEFT);$startDivision=SpecialCategoryService::isSpecial((string)$round['division'])?'novice':(string)$round['division'];
                $stmt=$pdo->prepare("INSERT INTO bdc_competitors(bdc_id,exact_name,normalised_name,dance_role,current_division,status,is_historical) VALUES(:bdc,:name,:normalised,:role,:division,'pending',0)");$stmt->execute(['bdc'=>$bdc,'name'=>$term,'normalised'=>$normalised,'role'=>$role,'division'=>$startDivision]);$comp=['id'=>(int)$pdo->lastInsertId(),'bdc_id'=>$bdc,'exact_name'=>$term,'current_division'=>$startDivision,'status'=>'pending'];
            }

            if($entryMode!=='create'){
                $points=$pdo->prepare("SELECT COALESCE(SUM(CASE WHEN division='novice' THEN points ELSE 0 END),0) novice_points,COALESCE(SUM(CASE WHEN division='intermediate' THEN points ELSE 0 END),0) intermediate_points,COALESCE(SUM(CASE WHEN division='advanced' THEN points ELSE 0 END),0) advanced_points FROM bdc_point_transactions WHERE competitor_id=:competitor AND dance_role IN(:role,'both')");$points->execute(['competitor'=>$comp['id'],'role'=>$role]);$p=$points->fetch()?:[];
                $history=$pdo->prepare("SELECT MAX(division='intermediate') competed_intermediate,MAX(division='advanced') competed_advanced,MAX(division='all_star') competed_all_star FROM (SELECT division FROM bdc_participant_results WHERE competitor_id=:c1 AND dance_role IN(:r1,'both') UNION ALL SELECT division FROM bdc_point_transactions WHERE competitor_id=:c2 AND dance_role IN(:r2,'both')) h");$history->execute(['c1'=>$comp['id'],'r1'=>$role,'c2'=>$comp['id'],'r2'=>$role]);$h=$history->fetch()?:[];
                $eligibility=DivisionProgressionService::eligibilityFor((string)$round['division'],(float)($p['novice_points']??0),(float)($p['intermediate_points']??0),(float)($p['advanced_points']??0),(string)($comp['current_division']??'unknown'),!empty($h['competed_intermediate']),!empty($h['competed_advanced']),!empty($h['competed_all_star']));
                if(!$eligibility['eligible'])throw new RuntimeException('Cannot add '.$comp['exact_name'].': '.$eligibility['reason']);
            }
            $pdo->prepare("INSERT INTO bdc_scoring_entries(round_id,competitor_id,dance_role,bib_number,display_name) VALUES(:round,:competitor,:role,:bib,:name) ON DUPLICATE KEY UPDATE bib_number=VALUES(bib_number),display_name=VALUES(display_name),entry_status='active'")->execute(['round'=>$roundId,'competitor'=>$comp['id'],'role'=>$role,'bib'=>$bib,'name'=>$comp['exact_name']]);
            autoAudit($pdo,$roundId,$userId,'entry_added',['competitor_id'=>$comp['id'],'role'=>$role,'bib'=>$bib,'automatic'=>true]);$notice=ucfirst($role).' added: '.$comp['exact_name'].' ('.$comp['bdc_id'].').';

        }elseif($action==='update_bib'){
            $entryId=(int)($_POST['entry_id']??0);$bib=(int)($_POST['bib_number']??0);if($entryId<1||$bib<1)throw new RuntimeException('Enter a valid bib number.');$entry=$pdo->prepare("SELECT dance_role,display_name FROM bdc_scoring_entries WHERE id=:id AND round_id=:round AND entry_status='active'");$entry->execute(['id'=>$entryId,'round'=>$roundId]);$row=$entry->fetch();if(!$row)throw new RuntimeException('Entry not found.');$dup=$pdo->prepare("SELECT display_name FROM bdc_scoring_entries WHERE round_id=:round AND dance_role=:role AND bib_number=:bib AND id<>:id AND entry_status='active' LIMIT 1");$dup->execute(['round'=>$roundId,'role'=>$row['dance_role'],'bib'=>$bib,'id'=>$entryId]);if($name=$dup->fetchColumn())throw new RuntimeException('Bib '.$bib.' is already assigned to '.$name.'.');$pdo->prepare('UPDATE bdc_scoring_entries SET bib_number=:bib WHERE id=:id AND round_id=:round')->execute(['bib'=>$bib,'id'=>$entryId,'round'=>$roundId]);$notice=$row['display_name'].' bib updated.';

        }elseif($action==='remove_entry'){
            $entryId=(int)($_POST['entry_id']??0);$pdo->prepare("UPDATE bdc_scoring_entries SET entry_status='withdrawn' WHERE id=:id AND round_id=:round")->execute(['id'=>$entryId,'round'=>$roundId]);$notice='Entry removed.';
        }
        $round=autoRound($pdo,$roundId);
    }
}catch(Throwable $e){$error=$e->getMessage();$round=[];}

if(!$round){http_response_code(500);?><!doctype html><html><head><meta charset="utf-8"><title>Automatic Scoring | BDC</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-5"><div class="alert alert-danger"><strong>Automatic Scoring could not open.</strong><br><?=e($error)?></div><a class="btn btn-dark" href="./?mode=automated">Back to Automatic Scoring</a></main></body></html><?php exit;}

$category=SpecialCategoryService::isSpecial((string)$round['division'])?SpecialCategoryService::label((string)$round['division']):ucfirst((string)$round['division']);
$setup=bdcRenderAutomaticCommonSetup($roundId);
AutomaticJudgeBrowserService::syncRound($pdo,$roundId);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Automatic Scoring | BDC Admin</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:#f4f6f9}.role-card{min-height:220px}</style></head><body><nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="../">BDC Admin</a><div class="d-flex gap-2"><a class="btn btn-outline-light btn-sm" href="./?mode=automated">All Rounds</a><a class="btn btn-warning btn-sm" href="https://bachatadancecouncil.com/">BDC Home</a></div></div></nav><main class="container-fluid py-4" style="max-width:1600px"><div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3"><div><div class="text-uppercase text-primary fw-bold small">Automatic Scoring</div><h1 class="h3 mb-1"><?=e((string)$round['event_name'])?></h1><div class="text-muted"><?=e($category)?> · <?=e(ucfirst((string)$round['round_type']))?></div></div><span class="badge text-bg-primary"><?=e(ucwords(str_replace('_',' ',(string)$round['status'])))?></span></div><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><?php if($notice):?><div class="alert alert-success"><?=e($notice)?></div><?php endif;?><?=$setup?><section class="card shadow-sm mb-4 border-dark"><div class="card-body"><div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2"><div><h2 class="h5 mb-1">Judge Live Scoring</h2><div class="small text-muted">This replaces Manual Score Entry. Judges score from their secure browser links while progress appears here live.</div></div><span class="badge text-bg-dark">LIVE</span></div><iframe title="Judge Live Scoring" src="judge-control.php?round_id=<?=$roundId?>" style="width:100%;height:690px;border:0;border-radius:10px;background:#fff"></iframe></div></section></main><script>function addJudge(){const w=document.getElementById('judgesWrap');if(!w)return;const i=w.querySelectorAll('.judge-row').length,d=document.createElement('div');d.className='row g-2 mb-2 judge-row align-items-center';d.innerHTML='<div class="col-md-2"><strong>Judge '+(i+1)+'</strong></div><div class="col-md-5"><input class="form-control" name="judge_name[]" placeholder="Judge name" required></div><div class="col-md-3"><select class="form-select" name="judge_scope[]"><option value="all">All</option><option value="leader">Leaders</option><option value="follower">Followers</option></select></div><div class="col-md-2"><label><input type="radio" name="chief_index" value="'+i+'"> Chief</label></div>';w.appendChild(d);}function updateTierSummary(){const t=document.getElementById('competitionTier'),o=document.getElementById('tierYesCount');if(t&&o)o.value=({1:5,2:10,3:15})[t.value]||10;}</script></body></html>