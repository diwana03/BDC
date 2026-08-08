<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\RelativePlacementCalculator;
use App\Services\ScoringRulesService;
use App\Services\SpecialCategoryService;
use App\Services\TestScoringGuardService;

Auth::requireAdmin();
if(!Auth::isSuperAdmin()){http_response_code(403);exit('Super Admin access required.');}
$pdo=Database::connection();
$error='';$notice='';

function tguard(string $table):void{TestScoringGuardService::assertWriteTable($table);}
function te(string $v):string{return htmlspecialchars($v,ENT_QUOTES,'UTF-8');}
function tAudit(PDO $pdo,int $roundId,string $action,array $details=[]):void{
    tguard('bdc_test_scoring_audit');
    $s=$pdo->prepare('INSERT INTO bdc_test_scoring_audit(round_id,user_id,action,details_json) VALUES(:r,:u,:a,:d)');
    $s->execute(['r'=>$roundId,'u'=>(int)(Auth::user()['id']??0)?:null,'a'=>$action,'d'=>json_encode($details,JSON_UNESCAPED_UNICODE)]);
}
function tColumnExists(PDO $pdo,string $table,string $column):bool{
    $s=$pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :column");$s->execute(['column'=>$column]);return (bool)$s->fetch();
}
function ensureSandboxSchema(PDO $pdo):void{
    tguard('bdc_test_scoring_rounds');
    if(!tColumnExists($pdo,'bdc_test_scoring_rounds','scoring_mode')){
        $pdo->exec("ALTER TABLE bdc_test_scoring_rounds ADD scoring_mode ENUM('manual','automated') NOT NULL DEFAULT 'manual' AFTER division");
    }
    try{$pdo->exec("ALTER TABLE bdc_test_scoring_rounds MODIFY division ENUM('novice','intermediate','advanced','all_star','bachata_rising','bachata_open','bachata_invitational') NOT NULL");}catch(Throwable $e){}
    tguard('bdc_test_scoring_judge_sessions');
    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_test_scoring_judge_sessions(
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,round_id BIGINT UNSIGNED NOT NULL,judge_id BIGINT UNSIGNED NOT NULL,
      status ENUM('not_started','scoring','submitted') NOT NULL DEFAULT 'not_started',last_saved_at DATETIME NULL,submitted_at DATETIME NULL,
      UNIQUE INDEX uq_test_judge_session(round_id,judge_id),INDEX idx_test_session_round(round_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function tRound(PDO $pdo,int $id):?array{
    $s=$pdo->prepare('SELECT r.*,e.name event_name,e.event_date FROM bdc_test_scoring_rounds r JOIN bdc_test_events e ON e.id=r.event_id WHERE r.id=:id');$s->execute(['id'=>$id]);return $s->fetch()?:null;
}
function tClearRound(PDO $pdo,int $roundId):void{
    foreach(['bdc_test_scoring_judge_sessions','bdc_test_scoring_final_results','bdc_test_scoring_final_marks','bdc_test_scoring_final_pairs','bdc_test_scoring_results','bdc_test_scoring_marks','bdc_test_scoring_judges','bdc_test_scoring_entries','bdc_test_scoring_audit'] as $table){
        tguard($table);$pdo->prepare("DELETE FROM `$table` WHERE round_id=:r")->execute(['r'=>$roundId]);
    }
    tguard('bdc_test_scoring_rounds');$pdo->prepare('DELETE FROM bdc_test_scoring_rounds WHERE id=:r')->execute(['r'=>$roundId]);
}
function tGenerateHeatsMarks(PDO $pdo,int $roundId,bool $automatic):int{
    $judges=$pdo->prepare('SELECT * FROM bdc_test_scoring_judges WHERE round_id=:r ORDER BY judge_order');$judges->execute(['r'=>$roundId]);$judges=$judges->fetchAll();
    $entries=$pdo->prepare("SELECT * FROM bdc_test_scoring_entries WHERE round_id=:r AND entry_status='active' ORDER BY dance_role,bib_number");$entries->execute(['r'=>$roundId]);$entries=$entries->fetchAll();
    if(count($judges)<ScoringRulesService::MINIMUM_JUDGES_PER_ROLE)throw new RuntimeException('Generate at least 3 judges first.');
    if(!$entries)throw new RuntimeException('Generate competitors first.');
    tguard('bdc_test_scoring_marks');
    $up=$pdo->prepare("INSERT INTO bdc_test_scoring_marks(round_id,entry_id,judge_id,mark_type,alt_rank,weighted_score,updated_by)
      VALUES(:r,:e,:j,:type,:alt,:weight,:u) ON DUPLICATE KEY UPDATE mark_type=VALUES(mark_type),alt_rank=VALUES(alt_rank),weighted_score=VALUES(weighted_score),updated_by=VALUES(updated_by),updated_at=NOW()");
    $count=0;
    foreach($judges as $judge){
        $scope=(string)($judge['scoring_scope']??'all');
        foreach($entries as $entry){
            if($scope!=='all'&&$scope!==$entry['dance_role'])continue;
            $roll=random_int(1,100);$type='blank';$alt=null;
            if($roll<=35)$type='yes';elseif($roll<=55){$type='alt';$alt=1;}elseif($roll<=72){$type='alt';$alt=2;}elseif($roll<=85){$type='alt';$alt=3;}
            $up->execute(['r'=>$roundId,'e'=>$entry['id'],'j'=>$judge['id'],'type'=>$type,'alt'=>$alt,'weight'=>ScoringRulesService::markWeight($type,$alt),'u'=>(int)(Auth::user()['id']??0)?:null]);$count++;
        }
        if($automatic){
            tguard('bdc_test_scoring_judge_sessions');
            $pdo->prepare("INSERT INTO bdc_test_scoring_judge_sessions(round_id,judge_id,status,last_saved_at,submitted_at) VALUES(:r,:j,'submitted',NOW(),NOW()) ON DUPLICATE KEY UPDATE status='submitted',last_saved_at=NOW(),submitted_at=NOW()")
                ->execute(['r'=>$roundId,'j'=>$judge['id']]);
        }
    }
    return $count;
}
function tCalculateHeats(PDO $pdo,array $round):void{
    $rid=(int)$round['id'];
    $judges=$pdo->prepare('SELECT * FROM bdc_test_scoring_judges WHERE round_id=:r ORDER BY judge_order');$judges->execute(['r'=>$rid]);$judges=$judges->fetchAll();
    if(count($judges)<3)throw new RuntimeException('At least 3 judges are required.');
    $chief=array_values(array_filter($judges,static fn($j)=>(int)$j['is_chief']===1));if(count($chief)!==1)throw new RuntimeException('Exactly one Chief Judge is required.');
    $entries=$pdo->prepare("SELECT * FROM bdc_test_scoring_entries WHERE round_id=:r AND entry_status='active' ORDER BY dance_role,bib_number");$entries->execute(['r'=>$rid]);$entries=$entries->fetchAll();if(!$entries)throw new RuntimeException('No competitors.');
    $mark=$pdo->prepare('SELECT judge_id,weighted_score FROM bdc_test_scoring_marks WHERE round_id=:r AND entry_id=:e');
    $rows=['leader'=>[],'follower'=>[]];
    foreach($entries as $entry){
        $assigned=array_values(array_filter($judges,static fn($j)=>in_array((string)($j['scoring_scope']??'all'),['all',$entry['dance_role']],true)));
        if(count($assigned)<ScoringRulesService::MINIMUM_JUDGES_PER_ROLE)throw new RuntimeException(ucfirst($entry['dance_role']).' panel requires at least 3 judges.');
        $assignedIds=array_map('intval',array_column($assigned,'id'));$chiefId=0;foreach($assigned as $j)if((int)$j['is_chief']===1)$chiefId=(int)$j['id'];
        $mark->execute(['r'=>$rid,'e'=>$entry['id']]);$total=0.0;$chiefScore=0.0;
        foreach($mark->fetchAll() as $m){if(!in_array((int)$m['judge_id'],$assignedIds,true))continue;$total+=(float)$m['weighted_score'];if((int)$m['judge_id']===$chiefId)$chiefScore=(float)$m['weighted_score'];}
        $rows[$entry['dance_role']][]=['entry'=>$entry,'total'=>$total,'chief'=>$chiefScore];
    }
    tguard('bdc_test_scoring_results');$pdo->prepare('DELETE FROM bdc_test_scoring_results WHERE round_id=:r')->execute(['r'=>$rid]);
    $ins=$pdo->prepare("INSERT INTO bdc_test_scoring_results(round_id,entry_id,total_score,chief_score,rank_number,result_status,alternate_rank,generated_version) VALUES(:r,:e,:t,:c,:rank,:status,:alt,1)");
    foreach(['leader','follower'] as $role){
        $list=$rows[$role];usort($list,static fn($a,$b)=>($b['total']<=>$a['total'])?:($b['chief']<=>$a['chief']));$callback=min((int)$round['callback_count'],count($list));
        foreach($list as $i=>$item){$pos=$i+1;$status=$pos<=$callback?'callback':($pos<=$callback+ScoringRulesService::ALTERNATE_COUNT?'alternate':'eliminated');$alt=$status==='alternate'?$pos-$callback:null;$ins->execute(['r'=>$rid,'e'=>$item['entry']['id'],'t'=>$item['total'],'c'=>$item['chief'],'rank'=>$pos,'status'=>$status,'alt'=>$alt]);}
    }
    tguard('bdc_test_scoring_rounds');$pdo->prepare("UPDATE bdc_test_scoring_rounds SET status='awaiting_decision',generated_version=generated_version+1 WHERE id=:r")->execute(['r'=>$rid]);
}
function tGenerateFinal(PDO $pdo,array $round):void{
    $rid=(int)$round['id'];
    $leaders=$pdo->prepare("SELECT id FROM bdc_test_scoring_entries WHERE round_id=:r AND dance_role='leader' AND entry_status='active' ORDER BY bib_number LIMIT 15");$leaders->execute(['r'=>$rid]);$leaders=$leaders->fetchAll(PDO::FETCH_COLUMN);
    $followers=$pdo->prepare("SELECT id FROM bdc_test_scoring_entries WHERE round_id=:r AND dance_role='follower' AND entry_status='active' ORDER BY bib_number LIMIT 15");$followers->execute(['r'=>$rid]);$followers=$followers->fetchAll(PDO::FETCH_COLUMN);
    $n=min(count($leaders),count($followers));if($n<2)throw new RuntimeException('Generate at least 2 Leaders and 2 Followers for a Final test.');
    tguard('bdc_test_scoring_final_pairs');$pdo->prepare('DELETE FROM bdc_test_scoring_final_pairs WHERE round_id=:r')->execute(['r'=>$rid]);
    $pairIns=$pdo->prepare("INSERT INTO bdc_test_scoring_final_pairs(round_id,pair_number,leader_entry_id,follower_entry_id,pairing_status,created_by) VALUES(:r,:n,:l,:f,'confirmed',:u)");
    $pairIds=[];for($i=0;$i<$n;$i++){$pairIns->execute(['r'=>$rid,'n'=>$i+1,'l'=>$leaders[$i],'f'=>$followers[$i],'u'=>(int)(Auth::user()['id']??0)?:null]);$pairIds[]=(int)$pdo->lastInsertId();}
    $judges=$pdo->prepare('SELECT id,is_chief FROM bdc_test_scoring_judges WHERE round_id=:r ORDER BY judge_order');$judges->execute(['r'=>$rid]);$judges=$judges->fetchAll();if(count($judges)<3)throw new RuntimeException('Generate at least 3 judges first.');
    tguard('bdc_test_scoring_final_marks');$pdo->prepare('DELETE FROM bdc_test_scoring_final_marks WHERE round_id=:r')->execute(['r'=>$rid]);$mark=$pdo->prepare('INSERT INTO bdc_test_scoring_final_marks(round_id,pair_id,judge_id,rank_value,updated_by) VALUES(:r,:p,:j,:rank,:u)');
    $marks=[];foreach($judges as $j){$ranks=range(1,$n);shuffle($ranks);foreach($pairIds as $i=>$pairId){$rank=$ranks[$i];$marks[$pairId][(int)$j['id']]=$rank;$mark->execute(['r'=>$rid,'p'=>$pairId,'j'=>$j['id'],'rank'=>$rank,'u'=>(int)(Auth::user()['id']??0)?:null]);}}
    $judgeIds=array_map('intval',array_column($judges,'id'));$chiefId=0;foreach($judges as $j)if((int)$j['is_chief']===1)$chiefId=(int)$j['id'];if(!$chiefId)throw new RuntimeException('Select one Chief Judge.');
    $final=RelativePlacementCalculator::calculate($pairIds,$judgeIds,$chiefId,$marks);
    tguard('bdc_test_scoring_final_results');$pdo->prepare('DELETE FROM bdc_test_scoring_final_results WHERE round_id=:r')->execute(['r'=>$rid]);$ins=$pdo->prepare('INSERT INTO bdc_test_scoring_final_results(round_id,pair_id,final_rank,majority_level,majority_count,placement_sum,chief_rank,decision_json) VALUES(:r,:p,:rank,:level,:count,:sum,:chief,:json)');
    foreach($final as $row)$ins->execute(['r'=>$rid,'p'=>$row['pair_id'],'rank'=>$row['final_rank'],'level'=>$row['level'],'count'=>$row['count'],'sum'=>$row['sum'],'chief'=>$row['chief_rank'],'json'=>json_encode($row,JSON_UNESCAPED_UNICODE)]);
    tguard('bdc_test_scoring_rounds');$pdo->prepare("UPDATE bdc_test_scoring_rounds SET status='scores_submitted' WHERE id=:r")->execute(['r'=>$rid]);
}

ensureSandboxSchema($pdo);
$roundId=(int)($_GET['round_id']??$_POST['round_id']??0);
try{
 if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Invalid security token.');$action=(string)($_POST['action']??'');
  if($action==='create_test'){
    $division=(string)($_POST['division']??'novice');$mode=(string)($_POST['scoring_mode']??'manual');$type=(string)($_POST['round_type']??'heats');
    $allowed=array_merge(['novice','intermediate','advanced'],array_keys(SpecialCategoryService::categories()));if(!in_array($division,$allowed,true))throw new RuntimeException('Invalid category.');if(!in_array($mode,['manual','automated'],true))throw new RuntimeException('Invalid scoring mode.');if(!in_array($type,['heats','final'],true))throw new RuntimeException('Invalid round type.');
    tguard('bdc_test_events');$name='TEST SANDBOX '.date('Y-m-d H:i:s');$slug='test-sandbox-'.date('YmdHis').'-'.random_int(100,999);$pdo->prepare("INSERT INTO bdc_test_events(name,normalised_name,slug,event_date,status) VALUES(:n,:nn,:s,CURDATE(),'draft')")->execute(['n'=>$name,'nn'=>strtolower($name),'s'=>$slug]);$eventId=(int)$pdo->lastInsertId();
    $tier=ScoringRulesService::tierFromRoleCounts(10,10);$w=ScoringRulesService::weights();tguard('bdc_test_scoring_rounds');$pdo->prepare("INSERT INTO bdc_test_scoring_rounds(event_id,round_type,division,scoring_mode,yes_count,callback_count,yes_weight,alt1_weight,alt2_weight,alt3_weight,status,created_by) VALUES(:e,:rt,:d,:m,:yes,:cb,:yw,:a1,:a2,:a3,'draft',:u)")->execute(['e'=>$eventId,'rt'=>$type,'d'=>$division,'m'=>$mode,'yes'=>$tier['yes_count'],'cb'=>$tier['yes_count'],'yw'=>$w['yes'],'a1'=>$w['alt1'],'a2'=>$w['alt2'],'a3'=>$w['alt3'],'u'=>(int)(Auth::user()['id']??0)?:null]);$roundId=(int)$pdo->lastInsertId();tAudit($pdo,$roundId,'sandbox_created',['mode'=>$mode,'division'=>$division]);$notice='Test sandbox created.';
  }elseif($action==='generate_competitors'){
    $r=tRound($pdo,$roundId);if(!$r)throw new RuntimeException('Test round not found.');$leaders=max(1,min(100,(int)($_POST['leaders']??10)));$followers=max(1,min(100,(int)($_POST['followers']??10)));
    tguard('bdc_test_scoring_entries');$pdo->prepare('DELETE FROM bdc_test_scoring_entries WHERE round_id=:r')->execute(['r'=>$roundId]);$ins=$pdo->prepare("INSERT INTO bdc_test_scoring_entries(round_id,competitor_id,dance_role,bib_number,display_name,entry_status) VALUES(:r,:c,:role,:bib,:name,'active')");
    foreach(['leader'=>$leaders,'follower'=>$followers] as $role=>$count){$q=$pdo->prepare("SELECT id,exact_name FROM bdc_competitors WHERE status='active' AND dance_role IN(:role,'both') ORDER BY RAND() LIMIT $count");$q->execute(['role'=>$role]);$bib=1;foreach($q->fetchAll() as $c){$ins->execute(['r'=>$roundId,'c'=>$c['id'],'role'=>$role,'bib'=>$bib++,'name'=>$c['exact_name']]);}}
    if(!SpecialCategoryService::isSpecial((string)$r['division'])){$tier=ScoringRulesService::tierFromRoleCounts($leaders,$followers);tguard('bdc_test_scoring_rounds');$pdo->prepare('UPDATE bdc_test_scoring_rounds SET yes_count=:y,callback_count=:c,tier_manual_override=0 WHERE id=:r')->execute(['y'=>$tier['yes_count'],'c'=>$tier['yes_count'],'r'=>$roundId]);$notice='Real BDC competitors loaded. Tier '.$tier['tier'].' selected from the larger role count.';}else{$notice='Real BDC competitors loaded. Special-category fixed points remain independent of participant count.';}
  }elseif($action==='generate_judges'){
    $count=max(3,min(25,(int)($_POST['judges']??5)));tguard('bdc_test_scoring_judges');$pdo->prepare('DELETE FROM bdc_test_scoring_judges WHERE round_id=:r')->execute(['r'=>$roundId]);$ins=$pdo->prepare("INSERT INTO bdc_test_scoring_judges(round_id,judge_name,judge_order,is_chief,scoring_scope) VALUES(:r,:name,:ord,:chief,'all')");for($i=1;$i<=$count;$i++)$ins->execute(['r'=>$roundId,'name'=>'Test Judge '.$i,'ord'=>$i,'chief'=>$i===1?1:0]);$chief=(int)$pdo->lastInsertId();$q=$pdo->prepare('SELECT id FROM bdc_test_scoring_judges WHERE round_id=:r AND is_chief=1 LIMIT 1');$q->execute(['r'=>$roundId]);tguard('bdc_test_scoring_rounds');$pdo->prepare('UPDATE bdc_test_scoring_rounds SET chief_judge_id=:c WHERE id=:r')->execute(['c'=>(int)$q->fetchColumn(),'r'=>$roundId]);$notice=$count.' judges generated.';
  }elseif($action==='generate_scores'){
    $r=tRound($pdo,$roundId);if(!$r)throw new RuntimeException('Round not found.');if($r['round_type']==='final'){tGenerateFinal($pdo,$r);$notice='Random Final rankings generated using the production Relative Placement calculator.';}else{$count=tGenerateHeatsMarks($pdo,$roundId,$r['scoring_mode']==='automated');tCalculateHeats($pdo,$r);$notice=$count.' Heats marks generated using shared BDC weights. '.($r['scoring_mode']==='automated'?'Automatic judge sessions simulated as submitted.':'Manual input path simulated.');}
  }elseif($action==='clear_test'){
    $r=tRound($pdo,$roundId);if($r){$eventId=(int)$r['event_id'];tClearRound($pdo,$roundId);tguard('bdc_test_events');$pdo->prepare('DELETE FROM bdc_test_events WHERE id=:e')->execute(['e'=>$eventId]);}$roundId=0;$notice='Test finished and all sandbox data cleared. Official BDC data was not changed.';
  }
 }
}catch(Throwable $e){$error=$e->getMessage();}

$round=$roundId?tRound($pdo,$roundId):null;$entries=['leader'=>[],'follower'=>[]];$judges=[];$results=[];$sessions=[];$finalResults=[];
if($round){$s=$pdo->prepare("SELECT se.*,c.bdc_id,c.current_division FROM bdc_test_scoring_entries se LEFT JOIN bdc_competitors c ON c.id=se.competitor_id WHERE se.round_id=:r AND se.entry_status='active' ORDER BY se.dance_role,se.bib_number");$s->execute(['r'=>$roundId]);foreach($s->fetchAll() as $x)$entries[$x['dance_role']][]=$x;$s=$pdo->prepare('SELECT * FROM bdc_test_scoring_judges WHERE round_id=:r ORDER BY judge_order');$s->execute(['r'=>$roundId]);$judges=$s->fetchAll();$s=$pdo->prepare("SELECT se.dance_role,se.bib_number,se.display_name,sr.* FROM bdc_test_scoring_results sr JOIN bdc_test_scoring_entries se ON se.id=sr.entry_id WHERE sr.round_id=:r ORDER BY se.dance_role,sr.rank_number");$s->execute(['r'=>$roundId]);$results=$s->fetchAll();$s=$pdo->prepare('SELECT * FROM bdc_test_scoring_judge_sessions WHERE round_id=:r ORDER BY judge_id');$s->execute(['r'=>$roundId]);$sessions=$s->fetchAll();$s=$pdo->prepare('SELECT * FROM bdc_test_scoring_final_results WHERE round_id=:r ORDER BY final_rank');$s->execute(['r'=>$roundId]);$finalResults=$s->fetchAll();}
$csrf=Csrf::token();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Scoring Test Sandbox | BDC</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:#f4f6f9}.safe{border-left:5px solid #198754}.automatic{background:#eef5ff}.manual{background:#f7f7f8}</style></head><body>
<nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="../">BDC Admin</a><div class="d-flex gap-2"><a class="btn btn-outline-light btn-sm" href="../">Dashboard</a></div></div></nav>
<main class="container-fluid py-4" style="max-width:1500px"><div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3"><div><h1 class="h3 mb-1">Scoring Test Sandbox</h1><div class="text-muted">Production rules · Real BDC competitors read-only · Disposable test scoring data</div></div><?php if($round):?><span class="badge <?=$round['scoring_mode']==='automated'?'text-bg-primary':'text-bg-dark'?>"><?=strtoupper(te($round['scoring_mode']==='automated'?'AUTOMATIC':'MANUAL'))?></span><?php endif;?></div>
<div class="alert alert-success safe"><strong>TEST WRITE FIREWALL ACTIVE.</strong> Real competitors, BDC IDs, divisions, history and points may be read. Official points, progression, publications and production scoring tables cannot be written by this sandbox.</div>
<?php if($error):?><div class="alert alert-danger"><?=te($error)?></div><?php endif;?><?php if($notice):?><div class="alert alert-info"><?=te($notice)?></div><?php endif;?>
<?php if(!$round):?>
<div class="card shadow-sm"><div class="card-body"><h2 class="h5">Start Fast Test</h2><form method="post" class="row g-3 align-items-end"><input type="hidden" name="_csrf" value="<?=te($csrf)?>"><input type="hidden" name="action" value="create_test"><div class="col-md-3"><label class="form-label">Scoring Mode</label><select class="form-select" name="scoring_mode"><option value="manual">Manual</option><option value="automated">Automatic</option></select></div><div class="col-md-3"><label class="form-label">Category</label><select class="form-select" name="division"><option value="novice">Novice</option><option value="intermediate">Intermediate</option><option value="advanced">Advanced</option><?php foreach(SpecialCategoryService::categories() as $key=>$label):?><option value="<?=te($key)?>"><?=te($label)?></option><?php endforeach;?></select></div><div class="col-md-3"><label class="form-label">Round</label><select class="form-select" name="round_type"><option value="heats">Heats</option><option value="final">Final</option></select></div><div class="col-md-3"><button class="btn btn-danger w-100">Create Test</button></div></form></div></div>
<?php else:?>
<div class="card shadow-sm mb-3 <?=$round['scoring_mode']==='automated'?'automatic':'manual'?>"><div class="card-body"><div class="row g-3 align-items-center"><div class="col-lg-6"><strong><?=te($round['event_name'])?></strong><br><?=te(SpecialCategoryService::isSpecial($round['division'])?SpecialCategoryService::label($round['division']):ucfirst($round['division']))?> · <?=te(ucfirst($round['round_type']))?></div><div class="col-lg-6 text-lg-end"><?php if(SpecialCategoryService::isSpecial($round['division'])):?><span class="badge text-bg-info">Fixed points: <?=te(implode(' · ',array_map(static fn($rank,$point)=>$rank.'='.$point,array_keys(SpecialCategoryService::schedule($round['division'])),SpecialCategoryService::schedule($round['division']))))?></span><?php else:?><span class="badge text-bg-secondary">YES per judge: <?=(int)$round['yes_count']?> · Weights <?=te(json_encode(ScoringRulesService::weights()))?></span><?php endif;?></div></div></div></div>
<div class="row g-3 mb-3"><div class="col-lg-4"><div class="card shadow-sm h-100"><div class="card-body"><h2 class="h6">1. Real BDC Competitors</h2><form method="post" class="row g-2"><input type="hidden" name="_csrf" value="<?=te($csrf)?>"><input type="hidden" name="action" value="generate_competitors"><input type="hidden" name="round_id" value="<?=$roundId?>"><div class="col-6"><label class="form-label">Leaders</label><input class="form-control" type="number" name="leaders" min="1" max="100" value="10"></div><div class="col-6"><label class="form-label">Followers</label><input class="form-control" type="number" name="followers" min="1" max="100" value="10"></div><div class="col-12"><button class="btn btn-outline-danger w-100">Load Competitors</button></div></form><div class="small text-muted mt-2">Reads directly from bdc_competitors. No profile copy is made.</div></div></div></div><div class="col-lg-4"><div class="card shadow-sm h-100"><div class="card-body"><h2 class="h6">2. Judges</h2><form method="post"><input type="hidden" name="_csrf" value="<?=te($csrf)?>"><input type="hidden" name="action" value="generate_judges"><input type="hidden" name="round_id" value="<?=$roundId?>"><label class="form-label">Judge count</label><input class="form-control" type="number" name="judges" min="3" max="25" value="5"><button class="btn btn-outline-dark w-100 mt-2">Generate Judges</button></form><div class="small text-muted mt-2">Minimum <?=ScoringRulesService::MINIMUM_JUDGES_PER_ROLE?> per role. Judge 1 is Chief.</div></div></div></div><div class="col-lg-4"><div class="card shadow-sm h-100"><div class="card-body"><h2 class="h6">3. Scores</h2><form method="post"><input type="hidden" name="_csrf" value="<?=te($csrf)?>"><input type="hidden" name="action" value="generate_scores"><input type="hidden" name="round_id" value="<?=$roundId?>"><button class="btn btn-primary w-100">Generate & Calculate <?= $round['scoring_mode']==='automated'?'Automatic':'Manual' ?> Scores</button></form><?php if($round['scoring_mode']==='automated'):?><div class="small text-muted mt-2">Simulates independent judge-browser submissions, then calculates from the same YES/A1/A2/A3 weights.</div><?php else:?><div class="small text-muted mt-2">Simulates admin-entered marks using the same shared BDC weights.</div><?php endif;?></div></div></div></div>
<div class="row g-3 mb-3"><div class="col-lg-6"><div class="card shadow-sm"><div class="card-header bg-primary-subtle"><strong>Leaders (<?=count($entries['leader'])?>)</strong></div><div class="card-body" style="max-height:300px;overflow:auto"><?php foreach($entries['leader'] as $x):?><div class="border-bottom py-1">#<?=$x['bib_number']?> <?=te($x['display_name'])?> <code><?=te((string)$x['bdc_id'])?></code> <span class="text-muted"><?=te((string)$x['current_division'])?></span></div><?php endforeach;?></div></div></div><div class="col-lg-6"><div class="card shadow-sm"><div class="card-header bg-danger-subtle"><strong>Followers (<?=count($entries['follower'])?>)</strong></div><div class="card-body" style="max-height:300px;overflow:auto"><?php foreach($entries['follower'] as $x):?><div class="border-bottom py-1">#<?=$x['bib_number']?> <?=te($x['display_name'])?> <code><?=te((string)$x['bdc_id'])?></code> <span class="text-muted"><?=te((string)$x['current_division'])?></span></div><?php endforeach;?></div></div></div></div>
<?php if($sessions):?><div class="card shadow-sm mb-3"><div class="card-body"><h2 class="h6">Automatic Judge Submission Simulation</h2><div class="d-flex gap-2 flex-wrap"><?php foreach($sessions as $s):?><span class="badge text-bg-success">Judge <?=$s['judge_id']?> · <?=te($s['status'])?></span><?php endforeach;?></div></div></div><?php endif;?>
<?php if($results):?><div class="card shadow-sm mb-3"><div class="card-body"><h2 class="h6">Heats Result</h2><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Role</th><th>Rank</th><th>Bib</th><th>Name</th><th>Total</th><th>Status</th></tr></thead><tbody><?php foreach($results as $x):?><tr><td><?=te($x['dance_role'])?></td><td><?=$x['rank_number']?></td><td><?=$x['bib_number']?></td><td><?=te($x['display_name'])?></td><td><?=number_format((float)$x['total_score'],1)?></td><td><?=te($x['result_status'])?></td></tr><?php endforeach;?></tbody></table></div></div></div><?php endif;?>
<?php if($finalResults):?><div class="card shadow-sm mb-3"><div class="card-body"><h2 class="h6">Final Relative Placement Result</h2><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Final Rank</th><th>Pair ID</th><th>Majority</th><th>Placement Sum</th><th>Chief Rank</th></tr></thead><tbody><?php foreach($finalResults as $x):?><tr><td><?=$x['final_rank']?></td><td><?=$x['pair_id']?></td><td><?=$x['majority_count']?> at <?=$x['majority_level']?></td><td><?=$x['placement_sum']?></td><td><?=$x['chief_rank']?></td></tr><?php endforeach;?></tbody></table></div></div></div><?php endif;?>
<form method="post" onsubmit="return confirm('Finish this test and permanently clear all sandbox scoring data? Official BDC data will remain unchanged.');"><input type="hidden" name="_csrf" value="<?=te($csrf)?>"><input type="hidden" name="action" value="clear_test"><input type="hidden" name="round_id" value="<?=$roundId?>"><button class="btn btn-danger">Finish Test & Clear All Test Data</button></form>
<?php endif;?></main></body></html>
