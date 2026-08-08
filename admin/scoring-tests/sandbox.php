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
    $s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND COLUMN_NAME=:column');
    $s->execute(['table'=>$table,'column'=>$column]);
    return (int)$s->fetchColumn()>0;
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

$roundId=(int)($_GET['round_id']??$_POST['round_id']??0);
try{
 ensureSandboxSchema($pdo);
 if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Invalid security token.');$action=(string)($_POST['action']??'');
  if($action==='create_test'){
    $division=(string)($_POST['division']??'novice');$mode=(string)($_POST['scoring_mode']??'manual');$type=(string)($_POST['round_type']??'heats');
    $allowed=array_merge(['novice','intermediate','advanced'],array_keys(SpecialCategoryService::categories()));if(!in_array($division,$allowed,true))throw new RuntimeException('Invalid category.');if(!in_array($mode,['manual','automated'],true))throw new RuntimeException('Invalid scoring mode.');if(!in_array($type,['heats','final'],true))throw new RuntimeException('Invalid round type.');
    tguard('bdc_test_events');$name='TEST SANDBOX '.date('Y-m-d H:i:s');$slug='test-sandbox-'.date('YmdHis').'-'.random_int(100,999);$pdo->prepare("INSERT INTO bdc_test_events(name,normalised_name,slug,event_date,status) VALUES(:n,:nn,:s,CURDATE(),'draft')")->execute(['n'=>$name,'nn'=>strtolower($name),'s'=>$slug]);$eventId=(int)$pdo->lastInsertId();
    $tier=ScoringRulesService::tierFromRoleCounts(10,10);$w=ScoringRulesService::weights();tguard('bdc_test_scoring_rounds');$pdo->prepare("INSERT INTO bdc_test_scoring_rounds(event_id,round_type,division,scoring_mode,yes_count,callback_count,yes_weight,alt1_weight,alt2_weight,alt3_weight,status,created_by) VALUES(:e,:rt,:d,:m,:yes,:cb,:yw,:a1,:a2,:a3,'draft',:u)")->execute(['e'=>$eventId,'rt'=>$type,'d'=>$division,'m'=>$mode,'yes'=>$tier['yes_count'],'cb'=>$tier