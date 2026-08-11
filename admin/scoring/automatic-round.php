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
            $pdo->prepare('UPDATE bdc_scoring_rounds SET yes_count=:yes,callback_count=:callback,tier_manual_override=1,yes_weight=10.00,alt1_weight=4.50,alt2_weight=4.30,alt3_weight=4.20 WHERE id=:id')->execute(['yes'=>$yes,'callback'=>$yes,'id'=>$roundId]);
            autoAudit($pdo,$roundId,$userId,'heats_settings_saved',['tier'=>$tier,'yes_count'=>$yes,'automatic'=>true]);$notice='Tier settings saved.';
        }elseif($action==='save_judges'){
            $names=$_POST['judge_name']??[];$scopes=$_POST['judge_scope']??[];$chief=(int)($_POST['chief_index']??-1);$rows=[];
            foreach($names as $index=>$raw){$name=trim((string)$raw);if($name==='')continue;$scope=(string)($scopes[$index]??'all');if(!in_array($scope,['all','leader','follower'],true))$scope='all';$rows[]=['name'=>$name,'scope'=>$scope,'original'=>(int)$index];}
            if(count($rows)<3)throw new RuntimeException('Minimum 3 judges required.');
            if(count($rows)!==count(array_unique(array_map(static fn($row)=>mb_strtolower($row['name']),$rows))))throw new RuntimeException('Judge names must be unique.');
            $chiefRow=null;foreach($rows as $i=>$row)if($row['original']===$chief){$chiefRow=$i;break;}if($chiefRow===null)throw new RuntimeException('Select one Chief Judge.');
            foreach(['leader','follower'] as $role){$count=count(array_filter($rows,static fn($row)=>in_array($row['scope'],['all',$role],true)));if($count<3)throw new RuntimeException(ucfirst($role).' panel must have at least 3 judges.');}
            $existingStmt=$pdo->prepare('SELECT * FROM bdc_scoring_judges WHERE round_id=:round ORDER BY judge_order');$existingStmt->execute(['round'=>$roundId]);$existing=$existingStmt->fetchAll();$byName=[];foreach($existing as $judge)$byName[mb_strtolower(trim((string)$judge['judge_name']))]=$judge;
            $pdo->beginTransaction();try{$used=[];$chiefId=0;$update=$pdo->prepare('UPDATE bdc_scoring_judges SET judge_name=:name,judge_order=:ord,is_chief=:chief,scoring_scope=:scope WHERE id=:id AND round_id=:round');$insert=$pdo->prepare('INSERT INTO bdc_scoring_judges(round_id,judge_name,judge_order,is_chief,scoring_scope) VALUES(:round,:name,:ord,:chief,:scope)');foreach($rows as $i=>$row){$key=mb_strtolower($row['name']);$isChief=$i===$chiefRow?1:0;if(isset($byName[$key])){$id=(int)$byName[$key]['id'];$update->execute(['name'=>$row['name'],'ord'=>$i+1,'chief'=>$isChief,'scope'=>$row['scope'],'id'=>$id,'round'=>$roundId]);}else{$insert->execute(['round'=>$roundId,'name'=>$row['name'],'ord'=>$i+1,'chief'=>$isChief,'scope'=>$row['scope']]);$id=(int)$pdo->lastInsertId();}$used[]=$id;if($isChief)$chiefId=$id;}if($used){$placeholders=implode(',',array_fill(0,count($used),'?'));$pdo->prepare("DELETE FROM bdc_scoring_judges WHERE round_id=? AND id NOT IN ($placeholders)")->execute(array_merge([$roundId],$used));}$pdo->prepare('UPDATE bdc_scoring_rounds SET chief_judge_id=:chief WHERE id=:round')->execute(['chief'=>$chiefId,'round'=>$roundId]);autoAudit($pdo,$roundId,$userId,'judges_saved',['count'=>count($rows),'automatic'=>true]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}$notice='Judges saved. Secure judge links are available below when competitors are added.';
        }
        $round=autoRound($pdo,$roundId);
    }
}catch(Throwable $e){$error=$e->getMessage();$round=[];}
if(!$round){http_response_code(500);?><!doctype html><html><head><meta charset="utf-8"><title>Automatic Scoring | BDC</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-5"><div class="alert alert-danger"><strong>Automatic Scoring could not open.</strong><br><?=e($error)?></div><a class="btn btn-dark" href="./?mode=automated">Back to Automatic Scoring</a></main></body></html><?php exit;}
$category=SpecialCategoryService::isSpecial((string)$round['division'])?SpecialCategoryService::label((string)$round['division']):ucfirst((string)$round['division']);$setup=bdcRenderAutomaticCommonSetup($roundId);AutomaticJudgeBrowserService::syncRound($pdo,$roundId);
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Automatic Scoring | BDC Admin</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:#f4f6f9}.role-card{min-height:220px}</style></head><body><nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="../">BDC Admin</a><div class="d-flex gap-2"><a class="btn btn-outline-light btn-sm" href="./?mode=automated">All Rounds</a><a class="btn btn-warning btn-sm" href="https://bachatadancecouncil.com/">BDC Home</a><a class="btn btn-danger btn-sm" href="../live-screen/control.php?round_id=<?=$roundId?>">Live Screen</a><a class="btn btn-outline-light btn-sm" href="javascript:history.back()">← Back</a><a class="btn btn-light btn-sm" href="../">⌂ Dashboard</a></div></div></nav><main class="container-fluid py-4" style="max-width:1600px"><div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3"><div><div class="text-uppercase text-primary fw-bold small">Automatic Scoring</div><h1 class="h3 mb-1"><?=e((string)$round['event_name'])?></h1><div class="text-muted"><?=e($category)?> · <?=e(ucfirst((string)$round['round_type']))?></div></div><span class="badge text-bg-primary"><?=e(ucwords(str_replace('_',' ',(string)$round['status'])))?></span></div><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><?php if($notice):?><div class="alert alert-success"><?=e($notice)?></div><?php endif;?><?=$setup?><?php $state=AutomaticJudgeBrowserService::roundState($pdo,$roundId);?><div class="card shadow-sm mt-3"><div class="card-header d-flex justify-content-between"><strong>Judge Live Scoring</strong><span class="badge text-bg-dark">LIVE</span></div><div class="card-body"><div class="small text-muted mb-3">Secure judge links and scoring progress.</div><?php foreach($state['judges'] as $j):?><div class="border rounded p-2 mb-2"><strong><?=e($j['judge_name'])?></strong><?=(int)$j['is_chief']?' ★':''?><span class="badge text-bg-secondary ms-2"><?=e(strtoupper($j['scoring_scope']))?></span><span class="float-end"><?=e($j['status'])?></span></div><?php endforeach;?></div></div></main></body></html>