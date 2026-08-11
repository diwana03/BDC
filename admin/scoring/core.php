<?php
declare(strict_types=1);

// Do not force a Content-Encoding value here. The shared-hosting web server may
// compress this large response after PHP finishes; overriding its gzip header
// with "identity" makes browsers display the compressed bytes as plain text.
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-transform, no-store, private');

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\DivisionProgressionService;
use App\Services\AutomaticScoringEngine;
use App\Services\SchemaUpdater;
use App\Services\ResultStorageService;

Auth::requireAdmin();

$scoringMode=(string)($_GET['mode']??'');
if(
 $_SERVER['REQUEST_METHOD']==='GET'
 && !isset($_GET['round_id'])
 && !in_array($scoringMode,['manual','automated'],true)
){
 $automatedSelected=$scoringMode==='automated';
 ?>
 <!doctype html>
 <html lang="en">
 <head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Select Scoring Mode | BDC Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
   body{min-height:100vh;background:#f4f6f9}
   .mode-shell{max-width:980px}
   .mode-card{height:100%;border:1px solid #dfe3e8;border-radius:18px;box-shadow:0 10px 28px rgba(15,23,42,.07)}
   .mode-icon{display:grid;width:58px;height:58px;place-items:center;border-radius:15px;background:#111827;color:#fff;font-size:1.7rem}
   .mode-card-future{background:#f8f9fb;color:#667085}
  </style>
 </head>
 <body>
 <nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="../">BDC Admin</a><a class="btn btn-outline-light btn-sm" href="../">Dashboard</a></div></nav>
 <main class="container mode-shell py-5">
  <div class="text-center mb-5">
   <h1 class="display-6 fw-bold">Select Scoring Mode</h1>
   <p class="text-muted mb-0">Choose how this competition will be scored.</p>
  </div>
  <?php if(Auth::canViewPastScores()):?><div class="text-end mb-3"><a class="btn btn-outline-dark" href="past.php">Past Event Scores</a></div><?php endif;?>
  <?php if($automatedSelected):?>
   <div class="alert alert-info text-center mb-4"><strong>Automatic Scoring</strong> is active for numeric Heats and Semi-Finals, with Relative Placement for Finals.</div>
  <?php endif;?>
  <div class="row g-4">
   <div class="col-md-6">
    <section class="card mode-card"><div class="card-body p-4 d-flex flex-column">
     <div class="mode-icon mb-4">✎</div>
     <h2 class="h3">Manual Scoring</h2>
     <p class="text-muted flex-grow-1">Open the existing BDC scoring dashboard and enter judges' scores manually.</p>
     <a class="btn btn-dark btn-lg" href="?mode=manual">Continue to Manual Scoring</a>
    </div></section>
   </div>
   <div class="col-md-6">
    <section class="card mode-card mode-card-future"><div class="card-body p-4 d-flex flex-column">
     <div class="mode-icon mb-4">⚙</div>
     <h2 class="h3">Automated Scoring</h2>
     <p class="flex-grow-1">Create the event and prepare competitors for the Automated Heats workflow.</p>
     <a class="btn btn-primary btn-lg" href="?mode=automated">Continue to Automated Scoring</a>
    </div></section>
   </div>
  </div>
 </main>
 </body>
 </html>
 <?php
 exit;
}

$pdo=Database::connection();

$userId=(int)(Auth::user()['id']??0);
$error=''; $notice='';


function ensureRegistrationDeskLink(PDO $pdo,int $eventId,string $division,int $userId):array{
 $stmt=$pdo->prepare("SELECT * FROM bdc_registration_desk_links WHERE event_id=:event AND division=:division LIMIT 1");
 $stmt->execute(['event'=>$eventId,'division'=>$division]);
 $existing=$stmt->fetch();
 if($existing)return $existing;

 $token=bin2hex(random_bytes(24));
 $hash=hash('sha256',$token);
 $hint=substr($token,0,8);
 $insert=$pdo->prepare("INSERT INTO bdc_registration_desk_links(event_id,division,token_hash,token_hint,created_by) VALUES(:event,:division,:hash,:hint,:user)");
 $insert->execute(['event'=>$eventId,'division'=>$division,'hash'=>$hash,'hint'=>$hint,'user'=>$userId?:null]);
 return ['id'=>(int)$pdo->lastInsertId(),'event_id'=>$eventId,'division'=>$division,'token_hash'=>$hash,'token_hint'=>$hint,'plain_token'=>$token,'is_enabled'=>1];
}
function registrationDeskUrl(array $link,int $roundId):string{
 $token=$link['plain_token']??($_SESSION['registration_desk_tokens'][(int)$link['id']]??'');
 if($token==='')return '';
 $appUrl=rtrim((string)Config::get('app.url',''),'/');
 $deskPath=url('registration-desk/?token='.rawurlencode($token).'&round_id='.$roundId);
 if($appUrl==='')return $deskPath;
 $originParts=parse_url($appUrl);
 if(!is_array($originParts)||!isset($originParts['scheme'],$originParts['host']))return $deskPath;
 $origin=$originParts['scheme'].'://'.$originParts['host'];
 if(isset($originParts['port']))$origin.=':'.(int)$originParts['port'];
 return $origin.$deskPath;
}

function auditScoring(PDO $pdo,int $roundId,int $userId,string $action,array $details=[]):void{
    $s=$pdo->prepare('INSERT INTO bdc_scoring_audit(round_id,user_id,action,details_json) VALUES(:r,:u,:a,:d)');
    $s->execute(['r'=>$roundId,'u'=>$userId?:null,'a'=>$action,'d'=>json_encode($details,JSON_UNESCAPED_UNICODE)]);
}
function loadRound(PDO $pdo,int $id):?array{
    $s=$pdo->prepare('SELECT r.*,e.name event_name,e.event_date,e.venue FROM bdc_scoring_rounds r JOIN bdc_events e ON e.id=r.event_id WHERE r.id=:id');
    $s->execute(['id'=>$id]); return $s->fetch()?:null;
}
function resultRoot():string{
    return ResultStorageService::root();
}
function safeFile(string $value):string{
    $v=preg_replace('/[^A-Za-z0-9_-]+/','-',trim($value)); return trim((string)$v,'-')?:'result';
}
function automaticTierFromRoleCounts(PDO $pdo,int $roundId):array{
    $stmt=$pdo->prepare("SELECT dance_role,COUNT(*) total FROM bdc_scoring_entries WHERE round_id=:round AND entry_status='active' GROUP BY dance_role");
    $stmt->execute(['round'=>$roundId]);
    $counts=['leader'=>0,'follower'=>0];
    foreach($stmt->fetchAll() as $row)$counts[$row['dance_role']]=(int)$row['total'];
    $largest=max($counts['leader'],$counts['follower']);
    $tier=$largest<=15?1:($largest<=30?2:3);
    $yes=[1=>5,2=>10,3=>15][$tier];
    return ['tier'=>$tier,'yes'=>$yes,'leaders'=>$counts['leader'],'followers'=>$counts['follower'],'largest'=>$largest];
}
function applyAutomaticTier(PDO $pdo,int $roundId,bool $force=false):array{
    $info=automaticTierFromRoleCounts($pdo,$roundId);
    $stmt=$pdo->prepare("SELECT tier_manual_override FROM bdc_scoring_rounds WHERE id=:round");
    $stmt->execute(['round'=>$roundId]);
    $manual=(int)$stmt->fetchColumn()===1;
    if($force||!$manual){
        $pdo->prepare("UPDATE bdc_scoring_rounds SET yes_count=:yes_count,callback_count=:callback_count WHERE id=:round")
            ->execute([
                'yes_count'=>$info['yes'],
                'callback_count'=>$info['yes'],
                'round'=>$roundId
            ]);
    }
    $info['manual']=$manual;
    return $info;
}

function computeResults(PDO $pdo,array $round,int $userId):void{
    $rid=(int)$round['id'];
    $judges=$pdo->prepare('SELECT * FROM bdc_scoring_judges WHERE round_id=:r ORDER BY judge_order');$judges->execute(['r'=>$rid]);$judges=$judges->fetchAll();
    if(count($judges)<3) throw new RuntimeException('At least 3 judges are required.');
    $chief=array_values(array_filter($judges,fn($j)=>(int)$j['is_chief']===1));
    if(count($chief)!==1) throw new RuntimeException('Exactly one Chief Judge is required.');
    $roleJudgeIds=[
      'leader'=>array_map('intval',array_column(array_values(array_filter($judges,fn($j)=>in_array($j['scoring_scope']??'all',['all','leader'],true))),'id')),
      'follower'=>array_map('intval',array_column(array_values(array_filter($judges,fn($j)=>in_array($j['scoring_scope']??'all',['all','follower'],true))),'id')),
    ];
    foreach(['leader','follower'] as $panelRole){
      if(count($roleJudgeIds[$panelRole])<3)throw new RuntimeException(ucfirst($panelRole).' panel requires at least 3 assigned judges.');
    }
    $entries=$pdo->prepare("SELECT * FROM bdc_scoring_entries WHERE round_id=:r AND entry_status='active' ORDER BY dance_role,bib_number");$entries->execute(['r'=>$rid]);$entries=$entries->fetchAll();
    if(!$entries) throw new RuntimeException('Add competitors before calculating.');
    $markStmt=$pdo->prepare('SELECT judge_id,weighted_score FROM bdc_scoring_marks WHERE entry_id=:e');
    $rows=[];
    foreach($entries as $entry){
        $markStmt->execute(['e'=>$entry['id']]);$marks=$markStmt->fetchAll();
        $total=0.0;$chiefScore=0.0;
        $assignedIds=$roleJudgeIds[$entry['dance_role']]??[];
        $roleChief=array_values(array_filter($judges,fn($j)=>(int)$j['is_chief']===1 && in_array((int)$j['id'],$assignedIds,true)));
        $chiefId=(int)($roleChief[0]['id']??0);
        foreach($marks as $m){
          if(!in_array((int)$m['judge_id'],$assignedIds,true))continue;
          $score=(float)$m['weighted_score'];$total+=$score;
          if((int)$m['judge_id']===$chiefId)$chiefScore=$score;
        }
        $rows[$entry['dance_role']][]=['entry'=>$entry,'total'=>$total,'chief'=>$chiefScore];
    }
    $pdo->beginTransaction();
    try{
        $version=(int)$round['generated_version']+1;
        $up=$pdo->prepare("INSERT INTO bdc_scoring_results(round_id,entry_id,total_score,chief_score,rank_number,result_status,alternate_rank,generated_version) VALUES(:r,:e,:t,:c,:rank,:st,:alt,:v) ON DUPLICATE KEY UPDATE total_score=VALUES(total_score),chief_score=VALUES(chief_score),rank_number=VALUES(rank_number),result_status=VALUES(result_status),alternate_rank=VALUES(alternate_rank),generated_version=VALUES(generated_version),updated_at=NOW()");
        foreach(['leader','follower'] as $role){
            $list=$rows[$role]??[];
            usort($list,function($a,$b){
                $totalOrder=$b['total']<=>$a['total'];
                return $totalOrder!==0?$totalOrder:($b['chief']<=>$a['chief']);
            });

            $callbackLimit=min((int)$round['callback_count'],count($list));
            $alternateLimit=min($callbackLimit+3,count($list));
            $i=0;

            while($i<count($list)){
                $groupStart=$i;
                $groupTotal=$list[$i]['total'];
                $groupChief=$list[$i]['chief'];

                while(
                    $i+1<count($list)
                    && $list[$i+1]['total']===$groupTotal
                    && $list[$i+1]['chief']===$groupChief
                ){
                    $i++;
                }

                $groupEnd=$i;
                $startPosition=$groupStart+1;
                $endPosition=$groupEnd+1;
                $rank=$startPosition;

                $crossesCallbackCutoff=$startPosition<=$callbackLimit && $endPosition>$callbackLimit;
                $crossesAlternateCutoff=$startPosition<=$alternateLimit && $endPosition>$alternateLimit;

                for($j=$groupStart;$j<=$groupEnd;$j++){
                    $status='eliminated';
                    $alt=null;

                    if($crossesCallbackCutoff || $crossesAlternateCutoff){
                        $status='tie_pending';
                    }elseif($endPosition<=$callbackLimit){
                        $status='callback';
                    }elseif($startPosition>$callbackLimit && $endPosition<=$alternateLimit){
                        $status='alternate';
                        $alt=$j-$callbackLimit+1;
                    }

                    $item=$list[$j];
                    $up->execute([
                        'r'=>$rid,
                        'e'=>$item['entry']['id'],
                        't'=>$item['total'],
                        'c'=>$item['chief'],
                        'rank'=>$rank,
                        'st'=>$status,
                        'alt'=>$alt,
                        'v'=>$version
                    ]);
                }

                $i++;
            }
        }
        $pdo->prepare("UPDATE bdc_scoring_rounds SET status='awaiting_decision',generated_version=:v WHERE id=:id")->execute(['v'=>$version,'id'=>$rid]);
        auditScoring($pdo,$rid,$userId,'results_generated',['version'=>$version]);
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function calculateRelativePlacement(PDO $pdo,int $roundId,int $userId):array{
    $judgeStmt=$pdo->prepare("SELECT id,judge_order,is_chief,judge_name FROM bdc_scoring_judges WHERE round_id=:r ORDER BY judge_order");
    $judgeStmt->execute(['r'=>$roundId]);
    $judges=$judgeStmt->fetchAll();
    if(count($judges)<3) throw new RuntimeException('At least 3 judges are required for Final scoring.');

    $pairStmt=$pdo->prepare("SELECT * FROM bdc_scoring_final_pairs WHERE round_id=:r AND pairing_status='confirmed' ORDER BY pair_number");
    $pairStmt->execute(['r'=>$roundId]);
    $pairs=$pairStmt->fetchAll();
    if(!$pairs) throw new RuntimeException('Confirm Final pairing before calculating rankings.');

    $markStmt=$pdo->prepare("SELECT pair_id,judge_id,rank_value FROM bdc_scoring_final_marks WHERE round_id=:r");
    $markStmt->execute(['r'=>$roundId]);
    $marks=[];
    foreach($markStmt->fetchAll() as $mark){
        $marks[(int)$mark['pair_id']][(int)$mark['judge_id']]=(int)$mark['rank_value'];
    }

    $pairIds=array_map(fn($pair)=>(int)$pair['id'],$pairs);
    $judgeIds=array_map(fn($judge)=>(int)$judge['id'],$judges);
    $chiefJudge=array_values(array_filter($judges,fn($judge)=>(int)$judge['is_chief']===1));
    $chiefId=(int)($chiefJudge[0]['id']??0);

    $final=\App\Services\RelativePlacementCalculator::calculate($pairIds,$judgeIds,$chiefId,$marks);

    $pdo->beginTransaction();
    try{
        $pdo->prepare("DELETE FROM bdc_scoring_final_results WHERE round_id=:r")->execute(['r'=>$roundId]);
        $insert=$pdo->prepare("INSERT INTO bdc_scoring_final_results(round_id,pair_id,final_rank,majority_level,majority_count,placement_sum,chief_rank,decision_json) VALUES(:r,:p,:rank,:level,:count,:sum,:chief,:json)");
        foreach($final as $row){
            $insert->execute(['r'=>$roundId,'p'=>$row['pair_id'],'rank'=>$row['final_rank'],'level'=>$row['level'],'count'=>$row['count'],'sum'=>$row['sum'],'chief'=>$row['chief_rank'],'json'=>json_encode($row,JSON_UNESCAPED_UNICODE)]);
        }
        auditScoring($pdo,$roundId,$userId,'final_relative_placement_calculated',['pairs'=>count($pairIds),'judges'=>count($judgeIds),'algorithm_version'=>'2.0.15']);
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    return $final;
}

function syncCallbacksToChildRound(PDO $pdo,array $source,int $childRoundId,int $userId):int{
    $callbackCount=$pdo->prepare("SELECT COUNT(*) FROM bdc_scoring_entries se JOIN bdc_scoring_results sr ON sr.entry_id=se.id AND sr.round_id=se.round_id WHERE se.round_id=:source_round AND se.entry_status='active' AND sr.result_status='callback'");
    $callbackCount->execute(['source_round'=>$source['id']]);$expected=(int)$callbackCount->fetchColumn();if($expected<1) throw new RuntimeException('No callback competitors were found in the submitted result.');
    $copyEntries=$pdo->prepare("INSERT INTO bdc_scoring_entries(round_id,competitor_id,dance_role,bib_number,display_name,entry_status) SELECT :new_round,se.competitor_id,se.dance_role,se.bib_number,se.display_name,'active' FROM bdc_scoring_entries se JOIN bdc_scoring_results sr ON sr.entry_id=se.id AND sr.round_id=se.round_id WHERE se.round_id=:source_round AND se.entry_status='active' AND sr.result_status='callback' ON DUPLICATE KEY UPDATE bib_number=VALUES(bib_number),display_name=VALUES(display_name),entry_status='active'");
    $copyEntries->execute(['new_round'=>$childRoundId,'source_round'=>$source['id']]);
    $actualStmt=$pdo->prepare("SELECT COUNT(*) FROM bdc_scoring_entries WHERE round_id=:r AND entry_status='active'");$actualStmt->execute(['r'=>$childRoundId]);$actual=(int)$actualStmt->fetchColumn();if($actual<1)throw new RuntimeException('Callbacks could not be transferred to the next round.');
    $judgeCount=$pdo->prepare("SELECT COUNT(*) FROM bdc_scoring_judges WHERE round_id=:r");$judgeCount->execute(['r'=>$childRoundId]);
    if((int)$judgeCount->fetchColumn()===0){$copyJudges=$pdo->prepare("INSERT INTO bdc_scoring_judges(round_id,judge_name,judge_order,is_chief,scoring_scope) SELECT :new_round,judge_name,judge_order,is_chief,scoring_scope FROM bdc_scoring_judges WHERE round_id=:source_round ORDER BY judge_order");$copyJudges->execute(['new_round'=>$childRoundId,'source_round'=>$source['id']]);$chief=$pdo->prepare("SELECT id FROM bdc_scoring_judges WHERE round_id=:r AND is_chief=1 LIMIT 1");$chief->execute(['r'=>$childRoundId]);$pdo->prepare("UPDATE bdc_scoring_rounds SET chief_judge_id=:chief WHERE id=:round_id")->execute(['chief'=>(int)$chief->fetchColumn() ?: null,'round_id'=>$childRoundId]);}
    auditScoring($pdo,$childRoundId,$userId,'callbacks_synced',['source_round_id'=>(int)$source['id'],'expected_callbacks'=>$expected,'active_child_entries'=>$actual]);return $actual;
}

function createNextScoringRound(PDO $pdo,array $source,string $nextType,int $userId):int{
    if(!in_array($nextType,['semifinal','final'],true)) throw new RuntimeException('Invalid next round.');
    $pending=$pdo->prepare("SELECT COUNT(*) FROM (SELECT se.dance_role,sr.rank_number,sr.total_score,sr.chief_score FROM bdc_scoring_results sr JOIN bdc_scoring_entries se ON se.id=sr.entry_id WHERE sr.round_id=:r AND sr.result_status='tie_pending' GROUP BY se.dance_role,sr.rank_number,sr.total_score,sr.chief_score HAVING COUNT(*)>1) unresolved_ties");$pending->execute(['r'=>$source['id']]);if((int)$pending->fetchColumn()>0)throw new RuntimeException('Resolve all callback ties before proceeding.');
    $existing=$pdo->prepare("SELECT id FROM bdc_scoring_rounds WHERE event_id=:e AND division=:d AND round_type=:t AND status<>'archived' ORDER BY id DESC LIMIT 1");$existing->execute(['e'=>$source['event_id'],'d'=>$source['division'],'t'=>$nextType]);$existingId=(int)$existing->fetchColumn();
    if($existingId>0){$pdo->beginTransaction();try{syncCallbacksToChildRound($pdo,$source,$existingId,$userId);$pdo->prepare("UPDATE bdc_scoring_rounds SET status='completed' WHERE id=:id")->execute(['id'=>$source['id']]);auditScoring($pdo,(int)$source['id'],$userId,'round_completed',['advanced_to_round_id'=>$existingId,'advanced_to_round_type'=>$nextType]);$pdo->commit();return $existingId;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}}
    $pdo->beginTransaction();try{$insert=$pdo->prepare("INSERT INTO bdc_scoring_rounds(event_id,parent_round_id,source_round_id,round_type,division,yes_count,callback_count,yes_weight,alt1_weight,alt2_weight,alt3_weight,status,created_by) VALUES(:e,:p,:s,:t,:d,:yes,:cb,:yw,:a1,:a2,:a3,'draft',:u)");$insert->execute(['e'=>$source['event_id'],'p'=>$source['id'],'s'=>$source['id'],'t'=>$nextType,'d'=>$source['division'],'yes'=>$source['yes_count'],'cb'=>$source['callback_count'],'yw'=>$source['yes_weight'],'a1'=>$source['alt1_weight'],'a2'=>$source['alt2_weight'],'a3'=>$source['alt3_weight'],'u'=>$userId?:null]);$newId=(int)$pdo->lastInsertId();syncCallbacksToChildRound($pdo,$source,$newId,$userId);$pdo->prepare("UPDATE bdc_scoring_rounds SET status='completed' WHERE id=:id")->execute(['id'=>$source['id']]);auditScoring($pdo,(int)$source['id'],$userId,'next_round_created',['next_round_id'=>$newId,'next_round_type'=>$nextType]);auditScoring($pdo,(int)$source['id'],$userId,'round_completed',['advanced_to_round_id'=>$newId,'advanced_to_round_type'=>$nextType]);auditScoring($pdo,$newId,$userId,'round_created_from_callbacks',['source_round_id'=>$source['id']]);$pdo->commit();return $newId;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

// Remaining restored engine sections intentionally preserved exactly through the original blob recovery.
// Recovery verification is done against the original blob before Phase 3 continues.
require __DIR__.'/core-restored-tail.php';