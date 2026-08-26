<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\DanceCupScoringService;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
Auth::requireAdmin();

$pdo=Database::connection();
$test=(string)($_GET['data_mode']??$_POST['data_mode']??'')==='test';
if($test&&!Auth::isSuperAdmin()){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'Super Admin required.']);exit;}
$id=(int)($_GET['id']??$_POST['id']??0);
$tables=DanceCupScoringService::tables($test);
$prefix=$test?'bdc_test_dance_cup':'bdc_dance_cup';

function dcApiReply(array $payload,int $status=200):never{
    http_response_code($status);
    echo json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function dcApiSnapshot(PDO $pdo,string $prefix,int $competition):array{
    $out=[];
    foreach(['entries','judges','marks','scoring_results'] as $name){
        $table=$prefix.'_'.$name;
        $query=$pdo->prepare("SELECT * FROM {$table} WHERE competition_id=:competition");
        $query->execute(['competition'=>$competition]);
        $out[$name]=$query->fetchAll();
    }
    return $out;
}

try{
    DanceCupScoringService::ensureWorkspaceTables($pdo,$test);
    $competition=$pdo->prepare("SELECT status,scoring_mode FROM {$tables['competitions']} WHERE id=:competition");
    $competition->execute(['competition'=>$id]);
    $competitionRow=$competition->fetch();
    if(!$competitionRow)throw new RuntimeException('Dance Cup category not found.');
    $status=(string)$competitionRow['status'];$savedWorkflow=(string)$competitionRow['scoring_mode'];
    if($savedWorkflow==='automatic')DanceCupScoringService::ensureAutomation($pdo,$id,$test);

    if($_SERVER['REQUEST_METHOD']==='POST'){
        if(!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Security check failed. Reload and try again.');
        $action=(string)($_POST['action']??'status');
        if(in_array($status,['submitted','pending_approval','approved'],true)&&!in_array($action,['checkpoint','approve_results'],true))throw new RuntimeException('Competition is submitted and locked.');

        if($action==='save_scores'){
            $marks=(array)($_POST['mark']??[]);
            $criteriaQuery=$pdo->prepare("SELECT id,maximum_points FROM {$tables['criteria']} WHERE competition_id=:competition");
            $criteriaQuery->execute(['competition'=>$id]);
            $limits=[];foreach($criteriaQuery->fetchAll() as $row)$limits[(int)$row['id']]=(float)$row['maximum_points'];
            $entryQuery=$pdo->prepare("SELECT id FROM {$prefix}_entries WHERE competition_id=:competition AND status='active'");
            $entryQuery->execute(['competition'=>$id]);$validEntries=array_fill_keys(array_map('intval',$entryQuery->fetchAll(PDO::FETCH_COLUMN)),true);
            $judgeQuery=$pdo->prepare("SELECT id FROM {$prefix}_judges WHERE competition_id=:competition");
            $judgeQuery->execute(['competition'=>$id]);$validJudges=array_fill_keys(array_map('intval',$judgeQuery->fetchAll(PDO::FETCH_COLUMN)),true);
            $upsert=$pdo->prepare("INSERT INTO {$prefix}_marks(competition_id,entry_id,judge_id,criterion_id,points) VALUES(:competition,:entry,:judge,:criterion,:points) ON DUPLICATE KEY UPDATE points=VALUES(points),updated_at=NOW()");
            $delete=$pdo->prepare("DELETE FROM {$prefix}_marks WHERE competition_id=:competition AND entry_id=:entry AND judge_id=:judge AND criterion_id=:criterion");
            $pdo->beginTransaction();
            foreach($marks as $entryId=>$judgeRows){
                $entryId=(int)$entryId;if(!isset($validEntries[$entryId]))throw new RuntimeException('A competitor in this score sheet is no longer active.');
                foreach((array)$judgeRows as $judgeId=>$criterionRows){
                    $judgeId=(int)$judgeId;if(!isset($validJudges[$judgeId]))throw new RuntimeException('A judge in this score sheet is no longer assigned.');
                    foreach((array)$criterionRows as $criterionId=>$raw){
                        $criterionId=(int)$criterionId;if(!isset($limits[$criterionId]))throw new RuntimeException('A scoring criterion is no longer available.');
                        if($raw===''){
                            $delete->execute(['competition'=>$id,'entry'=>$entryId,'judge'=>$judgeId,'criterion'=>$criterionId]);
                            continue;
                        }
                        if(!is_numeric($raw))throw new RuntimeException('Every score must be numeric.');
                        $points=(float)$raw;if($points<0||$points>$limits[$criterionId])throw new RuntimeException('A mark is outside its criterion maximum.');
                        $upsert->execute(['competition'=>$id,'entry'=>$entryId,'judge'=>$judgeId,'criterion'=>$criterionId,'points'=>$points]);
                    }
                }
            }
            $pdo->prepare("DELETE FROM {$prefix}_scoring_results WHERE competition_id=:competition")->execute(['competition'=>$id]);
            $pdo->commit();
            $message='Score draft saved without reloading the page.';
        }elseif($action==='calculate'){
            DanceCupScoringService::calculateResults($pdo,$id,$test);
            $message='Totals calculated and ranked.';
        }elseif($action==='checkpoint'){
            $label=trim((string)($_POST['checkpoint_label']??''))?:'Scoring checkpoint '.date('Y-m-d H:i');
            $query=$pdo->prepare("INSERT INTO {$prefix}_checkpoints(competition_id,label,snapshot_json,created_by) VALUES(:competition,:label,:snapshot,:user)");
            $query->execute(['competition'=>$id,'label'=>$label,'snapshot'=>json_encode(dcApiSnapshot($pdo,$prefix,$id),JSON_UNESCAPED_SLASHES),'user'=>(int)(Auth::user()['id']??0)?:null]);
            $message='Checkpoint saved.';
        }elseif($action==='submit'){
            $workflow=$savedWorkflow;
            $state=DanceCupScoringService::workflowState($pdo,$id,$test);
            if(!$state['all_marks_complete'])throw new RuntimeException('Complete every judge score before submitting the competition.');
            if(!$state['results_current'])throw new RuntimeException('Calculate and review the current results before submitting.');
            if($workflow==='automatic')$pdo->prepare("UPDATE {$prefix}_judge_sessions SET status='submitted',submitted_at=COALESCE(submitted_at,NOW()),last_seen_at=NOW(),started_at=COALESCE(started_at,NOW()) WHERE competition_id=:competition")->execute(['competition'=>$id]);
            $pdo->prepare("UPDATE {$tables['competitions']} SET status='pending_approval',submitted_by=:user,submitted_at=NOW(),approved_by=NULL,approved_at=NULL WHERE id=:competition")->execute(['competition'=>$id,'user'=>(int)(Auth::user()['id']??0)?:null]);
            $message=$workflow==='automatic'?'Competition and all completed judge sheets submitted and locked. Waiting for Super Admin approval.':'Competition submitted and locked. Waiting for Super Admin approval.';
        }elseif($action==='approve_results'){
            if(!Auth::isSuperAdmin())throw new RuntimeException('Only a Super Admin can approve and publish Dance Cup results.');
            if($test)throw new RuntimeException('Test results remain isolated and cannot be published.');
            $pdo->beginTransaction();
            DanceCupScoringService::approveResults($pdo,$id,(int)(Auth::user()['id']??0),false,trim((string)($_POST['approval_notes']??''))?:null);
            $pdo->commit();
            $message='Dance Cup result approved and published to permanent history.';
        }else{
            throw new RuntimeException('Unknown Dance Cup scoring action.');
        }
    }else{
        $message='Dance Cup scoring status loaded.';
    }

    dcApiReply(['ok'=>true,'message'=>$message,'state'=>DanceCupScoringService::workflowState($pdo,$id,$test)]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    dcApiReply(['ok'=>false,'error'=>$e->getMessage()],400);
}
