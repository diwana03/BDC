Warning: truncated output (original token count: 49280)
Total output lines: 2297

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
use App\Services\AutomaticJudgeBrowserService;
use App\Services\SchemaUpdater;
use App\Services\ResultStorageService;
use App\Services\SpecialCategoryService;
use App\Services\ScoringPageGuardService;
use App\Services\ScoringJudgeAssignmentService;
use App\Services\NextRankedFinalistService;
use App\Services\ScoringBackupService;
use App\Services\ScoringRosterCheckpointService;
use App\Services\RoleAdvancementService;

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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="../../public/css/scoring-premium.css?v=275" rel="stylesheet">
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
 <script src="../../public/js/final-pairing-sync.js?v=383" defer></script></body>
 </html>
 <?php
 exit;
}

$pdo=Database::connection();
ScoringPageGuardService::prepare($pdo,false);
set_exception_handler(static function(\Throwable $exception):void{
    ScoringPageGuardService::renderFailure($exception,false);
});

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
    $roleCountStmt=$pdo->prepare("SELECT dance_role,COUNT(*) total FROM bdc_scoring_entries WHERE round_id=:r AND entry_status='active' GROUP BY dance_role");
    $roleCountStmt->execute(['r'=>$rid]);$roleCounts=['leader'=>0,'follower'=>0];foreach($roleCountStmt->fetchAll() as $row)$roleCounts[$row['dance_role']]=(int)$row['total'];
    $rolePlan=RoleAdvancementService::roundPlan($roleCounts['leader'],$roleCounts['follower'],(int)$round['yes_count']);
    $judges=$pdo->prepare('SELECT * FROM bdc_scoring_judges WHERE round_id=:r ORDER BY judge_order');$judges->execute(['r'=>$rid]);$judges=$judges->fetchAll();
    $judgingRequired=($rolePlan['leader']['requires_judging']??false)||($rolePlan['follower']['requires_judging']??false);
    if($judgingRequired&&count($judges)<3) throw new RuntimeException('At least 3 judges are required.');
    $chief=array_values(array_filter($judges,fn($j)=>(int)$j['is_chief']===1));
    if($judgingRequired&&count($chief)!==1) throw new RuntimeException('Exactly one Chief Judge is required.');
    $roleJudgeIds=[
      'leader'=>array_map('intval',array_column(array_values(array_filter($judges,fn($j)=>in_array($j['scoring_scope']??'all',['all','leader'],true))),'id')),
      'follower'=>array_map('intval',array_column(array_values(array_filter($judges,fn($j)=>in_array($j['scoring_scope']??'all',['all','follower'],true))),'id')),
    ];
    foreach(['leader','follower'] as $panelRole){
      if(!($rolePlan[$panelRole]['requires_judging']??false))continue;
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
            usort($list,fn($a,$b)=>$b['total']<=>$a['total']);

            $callbackLimit=min((int)$round['callback_count'],count($list));
            $alternateLimit=min($callbackLimit+3,count($list));
            $i=0;

            while($i<count($list)){
                $groupStart=$i;
                $groupTotal=$list[$i]['total'];
                while(
                    $i+1<count($list)
                    && $list[$i+1]['total']===$groupTotal
                ){
                    $i++;
                }

                $groupEnd=$i;
                $startPosition=$groupStart+1;
                $endPosition=$groupEnd+1;
                $rank=$startPosition;

                $crossesCallbackCutoff=$startPosition<=$callbackLimit && $endPosition>$callbackLimit;
                $crossesAlternateCutoff=$startPosition<=$alternateLimit && $endPosition>$alternateLimit;
                $needsAlternateOrder=$groupEnd>$groupStart
                    && $startPosition>$callbackLimit
                    && $endPosition<=$alternateLimit;

                for($j=$groupStart;$j<=$groupEnd;$j++){
                    $status='eliminated';
                    $alt=null;

                    if($crossesCallbackCutoff || $crossesAlternateCutoff || $needsAlternateOrder){
                        $status='tie_pending';
                    }elseif($endPosition<=$callbackLimit){
                        // An exact tie entirely inside the callback zone is still a callback.
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
    $limitStmt=$pdo->prepare("SELECT callback_count FROM bdc_scoring_rounds WHERE id=:r");$limitStmt->execute(['r'=>$roundId]);$rankLimit=max(1,(int)$limitStmt->fetchColumn());
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

    $final=\App\Services\RelativePlacementCalculator::calculate(
        $pairIds,
        $judgeIds,
        $chiefId,
        $marks,
        min(count($pairIds), $rankLimit)
    );

    $pdo->beginTransaction();
    try{
        $pdo->prepare("DELETE FROM bdc_scoring_final_results WHERE round_id=:r")->execute(['r'=>$roundId]);
        $insert=$pdo->prepare("
          INSERT INTO bdc_scoring_final_results(
            round_id,pair_id,final_rank,majority_level,majority_count,placement_sum,chief_rank,decision_json
          ) VALUES(:r,:p,:rank,:level,:count,:sum,:chief,:json)
        ");
        foreach($final as $row){
            $insert->execute([
                'r'=>$roundId,
                'p'=>$row['pair_id'],
                'rank'=>$row['final_rank'],
                'level'=>$row['level'],
                'count'=>$row['count'],
                'sum'=>$row['sum'],
                'chief'=>$row['chief_rank'],
                'json'=>json_encode($row,JSON_UNESCAPED_UNICODE)
            ]);
        }
        auditScoring($pdo,$roundId,$userId,'final_relative_placement_calculated',[
          'pairs'=>count($pairIds),
          'judges'=>count($judgeIds),
          'algorithm_version'=>'2.0.15'
        ]);
        $pdo->commit();
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        throw $e;
    }

    return $final;
}

function syncCallbacksToChildRound(PDO $pdo,array $source,int $childRoundId,int $userId):int{
    $callbackCount=$pdo->prepare("
      SELECT COUNT(*)
      FROM bdc_scoring_entries se
      JOIN bdc_scoring_results sr
        ON sr.entry_id=se.id
       AND sr.round_id=se.round_id
      WHERE se.round_id=:source_round
        AND se.entry_status='active'
        AND sr.result_status='callback'
    ");
    $callbackCount->execute(['source_round'=>$source['id']]);
    $expected=(int)$callbackCount->fetchColumn();
    if($expected<1) throw new RuntimeException('No callback competitors were found in the submitted result.');

    // Existing child rounds must mirror the current callback result. Withdraw
    // previously transferred non-callbacks, while preserving explicit manual additions.
    $staleStmt=$pdo->prepare("
      SELECT child.id
      FROM bdc_scoring_entries child
      WHERE child.round_id=:child_round
        AND child.entry_status='active'
        AND EXISTS(
          SELECT 1 FROM bdc_scoring_entries source_entry
          WHERE source_entry.round_id=:source_round
            AND source_entry.competitor_id=child.competitor_id
            AND source_entry.dance_role=child.dance_role
        )
        AND NOT EXISTS(
          SELECT 1
          FROM bdc_scoring_entries source_entry
          JOIN bdc_scoring_results source_result
            ON source_result.entry_id=source_entry.id
           AND source_result.round_id=source_entry.round_id
          WHERE source_entry.round_id=:source_round_callback
            AND source_entry.competitor_id=child.competitor_id
            AND source_entry.dance_role=child.dance_role
            AND source_entry.entry_status='active'
            AND source_result.result_status='callback'
        )
        AND NOT EXISTS(
          SELECT 1 FROM bdc_scoring_audit manual_audit
          WHERE manual_audit.round_id=:child_round_audit
            AND manual_audit.action IN('entry_added','extra_finalist_added')
            AND JSON_VALID(manual_audit.details_json)
            AND CAST(JSON_UNQUOTE(JSON_EXTRACT(manual_audit.details_json,'$.competitor_id')) AS UNSIGNED)=child.competitor_id
        )
    ");
    $staleStmt->execute([
      'child_round'=>$childRoundId,
      'source_round'=>$source['id'],
      'source_round_callback'=>$source['id'],
      'child_round_audit'=>$childRoundId
    ]);
    $staleIds=array_map('intval',$staleStmt->fetchAll(PDO::FETCH_COLUMN));
    if($staleIds){
      $stalePlaceholders=implode(',',array_fill(0,count($staleIds),'?'));
      $pairStmt=$pdo->prepare("SELECT id FROM bdc_scoring_final_pairs WHERE round_id=? AND (leader_entry_id IN ($stalePlaceholders) OR follower_entry_id IN ($stalePlaceholders))");
      $pairStmt->execute(array_merge([$childRoundId],$staleIds,$staleIds));
      $stalePairIds=array_map('intval',$pairStmt->fetchAll(PDO::FETCH_COLUMN));
      if($stalePairIds){
        $pairPlaceholders=implode(',',array_fill(0,count($stalePairIds),'?'));
        $pdo->prepare("DELETE FROM bdc_scoring_final_marks WHERE pair_id IN ($pairPlaceholders)")->execute($stalePairIds);
        $pdo->prepare("DELETE FROM bdc_scoring_final_results WHERE pair_id IN ($pairPlaceholders)")->execute($stalePairIds);
        $pdo->prepare("DELETE FROM bdc_scoring_final_pairs WHERE id IN ($pairPlaceholders)")->execute($stalePairIds);
      }
      $pdo->prepare("UPDATE bdc_scoring_entries SET entry_status='withdrawn' WHERE id IN ($stalePlaceholders)")->execute($staleIds);
    }

    $copyEntries=$pdo->prepare("
      INSERT INTO bdc_scoring_entries(
        round_id,competitor_id,dance_role,bib_number,display_name,entry_status
      )
      SELECT
        :new_round,se.competitor_id,se.dance_role,se.bib_number,se.display_name,'active'
      FROM bdc_scoring_entries se
      JOIN bdc_scoring_results sr
        ON sr.entry_id=se.id
       AND sr.round_id=se.round_id
      WHERE se.round_id=:source_round
        AND se.entry_status='active'
        AND sr.result_status='callback'
      ON DUPLICATE KEY UPDATE
        bib_number=VALUES(bib_number),
        display_name=VALUES(display_name),
        entry_status='active'
    ");
    $copyEntries->execute([
      'new_round'=>$childRoundId,
      'source_round'=>$source['id']
    ]);

    $actualStmt=$pdo->prepare("
      SELECT COUNT(*)
      FROM bdc_scoring_entries
      WHERE round_id=:r AND entry_status='active'
    ");
    $actualStmt->execute(['r'=>$childRoundId]);
    $actual=(int)$actualStmt->fetchColumn();

    if($actual<1){
      throw new RuntimeException('Callbacks could not be transferred to the next round.');
    }

    $judgeCount=$pdo->prepare("SELECT COUNT(*) FROM bdc_scoring_judges WHERE round_id=:r");
    $judgeCount->execute(['r'=>$childRoundId]);
    if((int)$judgeCount->fetchColumn()===0){
      $copyJudges=$pdo->prepare("
        INSERT INTO bdc_scoring_judges(judge_id,round_id,judge_name,judge_order,is_chief,scoring_scope)
        SELECT judge_id,:new_round,judge_name,judge_order,is_chief,scoring_scope
        FROM bdc_scoring_judges
        WHERE round_id=:source_round
        ORDER BY judge_order
      ");
      $copyJudges->execute([
        'new_round'=>$childRoundId,
        'source_round'=>$source['id']
      ]);

      $chief=$pdo->prepare("
        SELECT id
        FROM bdc_scoring_judges
        WHERE round_id=:r AND is_chief=1
        LIMIT 1
      ");
      $chief->execute(['r'=>$childRoundId]);
      $pdo->prepare("
        UPDATE bdc_scoring_rounds
        SET chief_judge_id=:chief
        WHERE id=:round_id
      ")->execute([
        'chief'=>(int)$chief->fetchColumn() ?: null,
        'round_id'=>$childRoundId
      ]);
    }

    auditScoring($pdo,$childRoundId,$userId,'callbacks_synced',[
      'source_round_id'=>(int)$source['id'],
      'expected_callbacks'=>$expected,
      'stale_entries_withdrawn'=>count($staleIds),
      'active_child_entries'=>$actual
    ]);

    return $actual;
}

function nextRoundScheduleFromPost():string{
    $date=trim((string)($_POST['next_schedule_date']??''));
    $hour=(int)($_POST['next_schedule_hour']??0);
    $minute=(int)($_POST['next_schedule_minute']??-1);
    $period=strtoupper(trim((string)($_POST['next_schedule_period']??'')));
    $parsed=DateTime::createFromFormat('!Y-m-d',$date);
    if(!$parsed||$parsed->format('Y-m-d')!==$date||$hour<1||$hour>12||$minute<0||$minute>59||!in_array($period,['AM','PM'],true)){
        throw new RuntimeException('Select a valid next-round date and time.');
    }
    $hour24=$hour%12;
    if($period==='PM')$hour24+=12;
    return sprintf('%s %02d:%02d:00',$date,$hour24,$minute);
}

function createNextScoringRound(PDO $pdo,array $source,string $nextType,int $userId,string $scheduledAt=''):int{
    if(!in_array($nextType,['semifinal','final'],true)) throw new RuntimeException('Invalid next round.');
    $pending=$pdo->prepare("
      SELECT COUNT(*) FROM (
       SELECT se.dance_role,sr.rank_number,sr.total_score
       FROM bdc_scoring_results sr
       JOIN bdc_scoring_entries se ON se.id=sr.entry_id
       WHERE sr.round_id=:r AND sr.result_status='tie_pending'
       GROUP BY se.dance_role,sr.rank_number,sr.total_score
       HAVING COUNT(*)>1
      ) unresolved_ties
    ");
    $pending->execute(['r'=>$source['id']]);
    if((int)$pending->fetchColumn()>0) throw new RuntimeException('Resolve all callback ties before proceeding.');

    $existing=$pdo->prepare("SELECT id FROM bdc_scoring_rounds WHERE event_id=:e AND division=:d AND round_type=:t AND (parent_round_id=:parent OR source_round_id=:source) AND status<>'archived' ORDER BY id DESC LIMIT 1");
    $existing->execute(['e'=>$source['event_id'],'d'=>$source['division'],'t'=>$nextType,'parent'=>$source['id'],'source'=>$source['id']]);
    $existingId=(int)$existing->fetchColumn();
    if($existingId>0){
        $pdo->beginTransaction();
        try{
            $pdo->prepare("UPDATE bdc_scoring_rounds SET scoring_mode=:mode,scheduled_at=COALESCE(NULLIF(:scheduled,''),scheduled_at) WHERE id=:id")->execute(['mode'=>$source['scoring_mode']??'manual','scheduled'=>$scheduledAt,'id'=>$existingId]);
            syncCallbacksToChildRound($pdo,$source,$existingId,$userId);
            $pdo->prepare("UPDATE bdc_scoring_rounds SET status='completed' WHERE id=:id")
                ->execute(['id'=>$source['id']]);
            auditScoring($pdo,(int)$source['id'],$userId,'round_completed',[
              'advanced_to_round_id'=>$existingId,
              'advanced_to_round_type'=>$nextType
            ]);
            $pdo->commit();
            return $existingId;
        }catch(Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }
    }

    $pdo->beginTransaction();
    try{
        $insert=$pdo->prepare("INSERT INTO bdc_scoring_rounds(
          event_id,parent_round_id,source_round_id,round_type,scoring_mode,division,
          yes_count,callback_count,yes_weight,alt1_weight,alt2_weight,alt3_weight,
          scheduled_at,status,created_by
        ) VALUES(:e,:p,:s,:t,:mode,:d,:yes,:cb,:yw,:a1,:a2,:a3,:scheduled,'draft',:u)");
        $insert->execute([
          'e'=>$source['event_id'],'p'=>$source['id'],'s'=>$source['id'],'t'=>$nextType,'mode'=>$source['scoring_mode']??'manual',
          'd'=>$source['division'],'yes'=>$source['yes_count'],'cb'=>$source['callback_count'],
          'yw'=>$source['yes_weight'],'a1'=>$source['alt1_weight'],'a2'=>$source['alt2_weight'],
          'a3'=>$source['alt3_weight'],'scheduled'=>$scheduledAt!==''?$scheduledAt:null,'u'=>$userId?:null
        ]);
        $newId=(int)$pdo->lastInsertId();

        syncCallbacksToChildRound($pdo,$source,$newId,$userId);
        $pdo->prepare("UPDATE bdc_scoring_rounds SET status='completed' WHERE id=:id")
            ->execute(['id'=>$source['id']]);
        auditScoring($pdo,(int)$source['id'],$userId,'next_round_created',['next_round_id'=>$newId,'next_round_type'=>$nextType]);
        auditScoring($pdo,(int)$source['id'],$userId,'round_completed',[
          'advanced_to_round_id'=>$newId,
          'advanced_to_round_type'=>$nextType
        ]);
        auditScoring($pdo,$newId,$userId,'round_created_from_callbacks',['source_round_id'=>$source['id']]);
        $pdo->commit();
        return $newId;
    }catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function buildResultHtml(PDO $pdo,array $round):string{
    $rid=(int)$round['id'];
    $judges=$pdo->prepare('SELECT * FROM bdc_scoring_judges WHERE round_id=:r ORDER BY judge_order');$judges->execute(['r'=>$rid]);$judges=$judges->fetchAll();
    $q=$pdo->prepare("SELECT se.*,sr.total_score,sr.rank_number,sr.result_status,sr.alternate_rank FROM bdc_scoring_entries se LEFT JOIN bdc_scoring_results sr ON sr.entry_id=se.id AND sr.round_id=se.round_id WHERE se.round_id=:r AND se.entry_status='active' ORDER BY se.dance_role,sr.rank_number,se.bib_number");$q->execute(['r'=>$rid]);$all=$q->fetchAll();
    $by=['leader'=>[],'follower'=>[]];foreach($all as $x)$by[$x['dance_role']][]=$x;
    $logo=url('public/assets/bdc-logo.png');
    $isAutomatic=($round['scoring_mode']??'manual')==='automated';
    $table=function(string $role,array $rows)use($judges,$isAutomatic){ob_start();?><table><thead><tr><th><?=strtoupper($role)==='LEADER'?'LEAD #':'FOLLOW #'?></th><th><?=strtoupper($role).'S'?></th><?php foreach($judges as $j):?><th>J<?= (int)$j['judge_order'] ?><?= (int)$j['is_chief']?'*':'' ?></th><?php endforeach;?><th><?=$isAutomatic?'AVG':'TOTAL'?></th><th>CB</th></tr></thead><tbody><?php foreach($rows as $r):?><tr class="<?=e((string)$r['result_status'])?>"><td><?= (int)$r['bib_number'] ?></td><td><?=e($r['display_name'])?></td><?php foreach($judges as $j):?><td></td><?php endforeach;?><td><?=number_format((float)$r['total_score'],$isAutomatic?2:1)?></td><td><?=($r['result_status']==='callback')?(int)$r['rank_number']:(($r['result_status']==='alternate')?'A'.(int)$r['alternate_rank']:'')?></td></tr><?php endforeach;?></tbody></table><?php return ob_get_clean();};
    ob_start();?><!doctype html><html><head><meta charset="utf-8"><title>Heats Results</title><style>@page{size:A4 landscape;margin:8mm}body{font-family:Arial,sans-serif;color:#111;margin:0}.head{display:flex;justify-content:space-between;align-items:flex-start}.logo{width:90px}.title{font-weight:700;font-size:18px}.sub{font-weight:700;margin-top:5px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:12mm;margin-top:8mm}table{width:100%;border-collapse:collapse;font-size:10px}th,td{border:1px solid #111;padding:4px;text-align:center}th:nth-child(2),td:nth-child(2){text-align:left}.callback{background:#d1e7dd}.alternate{background:#fff3cd}.tie_pending{background:#f8d7da}.foot{margin-top:8mm;font-size:10px;display:flex;justify-content:space-between}.no-print{margin:10px}@media print{.no-print{display:none}}</style></head><body><div class="no-print"><button onclick="window.print()">Print / Save as PDF</button></div><div class="head"><div><div class="title"><?=e($round['event_name'])?></div><div class="sub"><?=strtoupper(e($round['division']))?> DIVISION - HEATS</div><div><?=e((string)$round['event_date'])?></div></div><img class="logo" src="<?=e($logo)?>"></div><div class="grid"><section><?=$table('leader',$by['leader'])?></section><section><?=$table('follower',$by['follower'])?></section></div><div class="foot"><div>Witness(es): ______________________________</div><div>Bachata Dance Council · Version <?= (int)$round['generated_version'] ?></div></div><script src="<?=e(url('admin/scoring/heats-live-v230.js?v=230'))?>"></script>
<div id="scoringProgressOverlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.82);z-index:10000;align-items:center;justify-content:center;color:#fff"><div style="background:#111827;padding:28px;border-radius:14px;min-width:320px;text-align:center"><div class="spinner-border mb-3" role="status"></div><h3 class="h5">Processing Scores</h3><div id="scoringProgressText">Saving scores…</div><div class="progress mt-3" style="height:10px"><div id="scoringProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width:20%"></div></div></div></div></body></html><?php return ob_get_clean();
}

$roundId=(int)($_GET['round_id']??$_POST['round_id']??0);
try{
 if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Invalid security token.');
  $action=(string)($_POST['action']??'');
  if($action!=='create_round' && !empty($_POST['round_id'])){
   $lockedRound=loadRound($pdo,(int)$_POST['round_id']);
   if($lockedRound && in_array((string)$lockedRound['status'],['completed','pending_approval','archived'],true) && !in_array($action,['reopen_completed_round','create_scoring_backup','restore_scoring_backup','delete_scoring_backup','delete_selected_scoring_backups'],true)){
    $message=$lockedRound['status']==='completed'
      ? 'This completed round is locked. Only a Scorer, Master Scorer or Super Admin can confirm a resubmission override.'
      : ($lockedRound['status']==='pending_approval'
      ? 'This competition is pending Super Admin approval and is temporarily read-only.'
      : 'This competition is archived and read-only. Only Super Admin rollback can reopen it.');
    throw new RuntimeException($message);
   }
  }
  if($action!=='create_round' && !in_array($action,['create_scoring_backup','restore_scoring_backup','delete_scoring_backup','delete_selected_scoring_backups','generate_emcee_link'],true) && !empty($_POST['round_id'])){
   ScoringBackupService::create($pdo,(int)$_POST['round_id'],false,$userId,'automatic',$action,'Before '.str_replace('_',' ',$action));
  }
  if($action==='create_scoring_backup'){
   if(!Auth::canManageScoringBackups())throw new RuntimeException('Only an Admin, Scorer, Master Scorer or Super Admin can create protected scoring backups.');
   $roundId=(int)($_POST['round_id']??0);if(!loadRound($pdo,$roundId))throw new RuntimeException('Scoring round not found.');
   $backupId=ScoringBackupService::create($pdo,$roundId,false,$userId,'manual','manual_backup',(string)($_POST['backup_label']??''));
   auditScoring($pdo,$roundId,$userId,'manual_scoring_backup_created',['backup_id'=>$backupId]);$notice='Protected scoring backup #'.$backupId.' created.';
  }elseif($action==='restore_scoring_backup'){
   if(!Auth::canManageScoringBackups())throw new RuntimeException('Only an Admin, Scorer, Master Scorer or Super Admin can restore scoring backups.');
   $roundId=(int)($_POST['round_id']??0);$confirmation=strtoupper(trim((string)($_POST['restore_confirmation']??'')));if($confirmation!=='RESTORE SCORES')throw new RuntimeException('Type RESTORE SCORES to confirm recovery.');
   $restored=ScoringBackupService::restore($pdo,(int)($_POST['backup_id']??0),$roundId,false,$userId,(string)($_POST['restore_reason']??''));$notice='Scoring backup #'.$restored['id'].' restored. A safety copy of the previous state was created first.';
  }elseif($action==='delete_scoring_backup'){
   if(!Auth::canManageScoringBackups())throw new RuntimeException('Only an Admin, Scorer, Master Scorer or Super Admin can delete scoring backups.');
   $roundId=(int)($_POST['round_id']??0);if(!loadRound($pdo,$roundId))throw new RuntimeException('Scoring round not found.');
   if(strtoupper(trim((string)($_POST['delete_confirmation']??'')))!=='DELETE BACKUP')throw new RuntimeException('Type DELETE BACKUP to confirm permanent deletion.');
   $deleted=ScoringBackupService::delete($pdo,(int)($_POST['backup_id']??0),$roundId,false,$userId,(string)($_POST['delete_reason']??''));$notice='Scoring backup #'.$deleted['id'].' permanently deleted. Current scores were not changed.';
  }elseif($action==='delete_selected_scoring_backups'){
   if(!Auth::canManageScoringBackups())throw new RuntimeException('Only an Admin, Scorer, Master Scorer or Super Admin can delete scoring backups.');
   $roundId=(int)($_POST['round_id']??0);if(!loadRound($pdo,$roundId))throw new RuntimeException('Scoring round not found.');
   if(strtoupper(trim((string)($_POST['delete_confirmation']??'')))!=='DELETE SELECTED')throw new RuntimeException('Type DELETE SELECTED to confirm permanent deletion.');
   $ids=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['backup_ids']??[])),static fn(int $id):bool=>$id>0)));if(!$ids)throw new RuntimeException('Select at least one scoring backup to delete.');
   $deleted=ScoringBackupService::deleteMany($pdo,$ids,$roundId,false,$userId,(string)($_POST['delete_reason']??''));$notice=$deleted['count'].' selected scoring backup'.($deleted['count']===1?'':'s').' permanently deleted. Current scores were not changed.';
  }elseif($action==='reopen_completed_round'){
   if(!Auth::canOverrideCompletedScores())throw new RuntimeException('Only a Scorer, Master Scorer or Super Admin can reopen a completed round.');
   $roundId=(int)($_POST['round_id']??0);
   $confirmation=strtoupper(trim((string)($_POST['resubmit_confirmation']??'')));
   $completed=loadRound($pdo,$roundId);
   if(!$completed||$completed['status']!=='completed')throw new RuntimeException('Completed round not found.');
   if($confirmation!=='RESUBMIT')throw new RuntimeException('Type RESUBMIT to confirm the scoring override.');
   $pdo->prepare("UPDATE bdc_scoring_rounds SET status='draft',locked_at=NULL,locked_by=NULL WHERE id=:id")->execute(['id'=>$roundId]);
   auditScoring($pdo,$roundId,$userId,'completed_round_reopened_for_resubmission',['confirmation'=>'RESUBMIT','child_rounds_preserved'=>true]);
   $notice='Completed round unlocked for correction. Submit the scores again when finished.';
  }elseif($action==='unlock_all_final_judges'){
   if(!Auth::canOverrideCompletedScores())throw new RuntimeException('Only a Scorer, Master Scorer or Super Admin can use the emergency unlock.');
   $roundId=(int)($_POST['round_id']??0);$reason=trim((string)($_POST['unlock_all_reason']??''));$confirmation=strtoupper(trim((string)($_POST['unlock_all_confirmation']??'')));
   $finalRound=loadRound($pdo,$roundId);
   if(!$finalRound||$finalRound['round_type']!=='final'||($finalRound['scoring_mode']??'manual')!=='automated')throw new RuntimeException('Automatic Final round not found.');
   if($confirmation!=='UNLOCK ALL')throw new RuntimeException('Type UNLOCK ALL to confirm the emergency override.');
   $unlocked=AutomaticJudgeBrowserService::unlockAllSubmitted($pdo,$roundId,$userId,$reason);
   $pdo->prepare("UPDATE bdc_scoring_rounds SET status=CASE WHEN status='scores_submitted' THEN 'draft' ELSE status END WHERE id=:id")->execute(['id'=>$roundId]);
   auditScoring($pdo,$roundId,$userId,'all_final_judge_scores_emergency_unlocked',['reason'=>$reason,'affected_count'=>$unlocked['count'],'judge_ids'=>$unlocked['judge_ids']]);
   $notice=$unlocked['count'].' locked judge score columns reopened. Existing placements were preserved and must be resubmitted.';
  }elseif($action==='create_round'){
   $createMode=(string)($_POST['scoring_mode']??'manual');if(!in_array($createMode,['manual','automated'],true))$createMode='manual';
   $eventId=(int)($_POST['event_id']??0);
   $newEventName=trim((string)($_POST['new_event_name']??''));
   $newEventDate=trim((string)($_POST['new_event_date']??''));
   $division=(string)($_POST['division']??'novice');
   $roundType=(string)($_POST['round_type']??'heats');
   if(!in_array($division,['novice','intermediate','advanced','all_star'],true))throw new RuntimeException('Invalid division.');
   if(!in_array($roundType,['heats','final'],true))throw new RuntimeException('Invalid round type.');
   if($eventId>0 && $newEventName!=='')throw new RuntimeException('Select an existing event or create a new event, not both.');
   if($eventId<1){
    if($newEventName==='')throw new RuntimeException('Select an existing event or enter a new event name.');
    if($newEventDate!=='' && !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$newEventDate))throw new RuntimeException('Enter the event date as YYYY-MM-DD.');
    $baseSlug=strtolower(trim((string)preg_replace('/[^a-z0-9]+/i','-',$newEventName),'-'));
    if($baseSlug==='')$baseSlug='event';
    $slug=$baseSlug;$n=2;
    $checkSlug=$pdo->prepare('SELECT COUNT(*) FROM bdc_events WHERE slug=:slug');
    while(true){$checkSlug->execute(['slug'=>$slug]);if(!(int)$checkSlug->fetchColumn())break;$slug=$baseSlug.'-'.$n++;}
    $eventInsert=$pdo->prepare("INSERT INTO bdc_events(name,normalised_name,slug,event_date,status) VALUES(:name,:normalised,:slug,NULLIF(:event_date,''),'draft')");
    $eventInsert->execute(['name'=>$newEventName,'normalised'=>strtolower($newEventName),'slug'=>$slug,'event_date'=>$newEventDate]);
    $eventId=(int)$pdo->lastInsertId();
   }
   $existing=$pdo->prepare("SELECT id FROM bdc_scoring_rounds WHERE event_id=:e AND division=:d AND round_type=:rt AND scoring_mode=:mode AND status<>'archived' ORDER BY id DESC LIMIT 1");
   $existing->execute(['e'=>$eventId,'d'=>$division,'rt'=>$roundType,'mode'=>$createMode]);
   $existingId=(int)$existing->fetchColumn();
   if($existingId>0){$roundId=$existingId;$notice=ucfirst($roundType).' round already exists. Existing round opened.';}
   else{
    $s=$pdo->prepare("INSERT INTO bdc_scoring_rounds(event_id,roun…29280 tokens truncated…d" value="<?=$roundId?>">
<div class="col-12">
<label class="form-label">Competition Tier</label>
<select class="form-select" name="competition_tier" id="competitionTier" onchange="updateTierSummary()">
<option value="1" <?=$currentTier===1?'selected':''?>>Tier 1 · 5–15 competitors</option>
<option value="2" <?=$currentTier===2?'selected':''?>>Tier 2 · 16–30 competitors</option>
<option value="3" <?=$currentTier===3?'selected':''?>>Tier 3 · 30+ competitors</option>
</select>
</div>
<div class="col-6">
<label class="form-label">YES per Judge</label>
<input class="form-control" id="tierYesCount" value="<?=$round['yes_count']?>" readonly>
</div>
<div class="col-6">
<label class="form-label">Alternates</label>
<input class="form-control" value="3" readonly>
</div>
<div class="col-12">
<div class="border rounded p-3 bg-light">
<div class="fw-semibold mb-2">Official BDC Weights · Locked</div>
<div class="row g-2 text-center">
<div class="col-3"><small class="text-muted d-block">YES</small><strong>10</strong></div>
<div class="col-3"><small class="text-muted d-block">ALT 1</small><strong>4.5</strong></div>
<div class="col-3"><small class="text-muted d-block">ALT 2</small><strong>4.3</strong></div>
<div class="col-3"><small class="text-muted d-block">ALT 3</small><strong>4.2</strong></div>
</div>
</div>
</div>
<div class="col-12"><small class="text-muted">Automatic tier uses the larger individual role count, not Leaders + Followers combined. Tier 1: 5–15, Tier 2: 16–30, Tier 3: 31+. Saving here creates a manual override.</small></div>
<div class="col-12"><button class="btn btn-outline-dark btn-sm">Save Tier Settings</button></div>
</form>
<?php endif;?>
</div></div></div>
<div class="col-lg-8"><div class="card shadow-sm h-100" id="judge-setup"><div class="card-body"><h2 class="h5">Judge Setup</h2><div class="small text-muted mb-3">Search the Judge Database or type a new name. New names automatically receive a Judge ID. Each role panel must contain at least 3 judges.</div><form method="post" id="judgesForm"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="save_judges"><input type="hidden" name="round_id" value="<?=$roundId?>"><div id="judgesWrap"><?php $display=$judges?:[['id'=>0,'judge_id'=>0,'judge_name'=>'','is_chief'=>1,'scoring_scope'=>'all'],['id'=>0,'judge_id'=>0,'judge_name'=>'','is_chief'=>0,'scoring_scope'=>'all'],['id'=>0,'judge_id'=>0,'judge_name'=>'','is_chief'=>0,'scoring_scope'=>'all']];foreach($display as $i=>$j):?><div class="row g-2 mb-2 judge-row align-items-center"><div class="col-md-2"><strong>Judge <?=$i+1?></strong><input type="hidden" name="judge_assignment_id[]" value="<?=(int)($j['id']??0)?>"><input type="hidden" name="judge_directory_id[]" value="<?=(int)($j['judge_id']??0)?>"></div><div class="col-md-5"><input class="form-control" name="judge_name[]" list="judgeDirectorySuggestions" value="<?=e($j['judge_name'])?>" placeholder="Search or type a new judge" required></div><div class="col-md-3"><select class="form-select" name="judge_scope[]"><?php foreach(['all'=>'All','leader'=>'Leaders','follower'=>'Followers'] as $scopeValue=>$scopeLabel):?><option value="<?=$scopeValue?>" <?=($j['scoring_scope']??'all')===$scopeValue?'selected':''?>><?=$scopeLabel?></option><?php endforeach;?></select></div><div class="col-md-2"><label><input type="radio" name="chief_index" value="<?=$i?>" <?=(int)$j['is_chief']?'checked':''?>> Chief</label></div></div><?php endforeach;?></div><div class="d-flex gap-2 flex-wrap"><button type="button" class="btn btn-outline-secondary btn-sm" onclick="addJudge()">+ Judge</button><button class="btn btn-dark btn-sm">Submit Judges</button><a class="btn btn-outline-primary btn-sm" href="print.php?round_id=<?=$roundId?>" target="_blank">Generate Judge Sheets</a></div></form></div></div></div></div>
<datalist id="judgeDirectorySuggestions"><?php foreach($judgeDirectory as $directoryJudge):$directoryName=(string)($directoryJudge['display_name']?:$directoryJudge['full_name']);?><option value="<?=e($directoryName)?>"><?=e((string)$directoryJudge['judge_code'].(!empty($directoryJudge['country'])?' · '.$directoryJudge['country']:''))?></option><?php endforeach;?></datalist>
<?php foreach(['leader'=>'Leader','follower'=>'Follower'] as $suggestionRole=>$suggestionLabel):?><datalist id="competitorSuggestions<?=ucfirst($suggestionRole)?>"><?php foreach($competitorSuggestions as $suggestion):if((string)$suggestion['dance_role']!==$suggestionRole)continue;?><option value="<?=e($suggestion['bdc_id'])?>"><?=e($suggestion['exact_name'].' · '.$suggestionLabel.($suggestion['status']==='pending'?' · Details pending':''))?></option><?php endforeach;?></datalist><?php endforeach;?>
<fieldset <?=$rosterSubmitted?'disabled':''?>><div class="row g-3 mb-4">
<div class="col-lg-6"><div class="card shadow-sm role-card"><div class="card-header bg-primary-subtle fw-semibold">Leaders</div><div class="card-body">
<form method="post" class="row g-2 mb-3"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="add_entry"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="dance_role" value="leader">
<div class="col-3"><input class="form-control" type="number" min="1" name="bib_number" value="<?=$nextBib['leader']?>" aria-label="Leader bib number" required><div class="form-text">Next suggested bib. You can overwrite it.</div></div>
<div class="col-9"><input class="form-control" name="competitor_search" list="competitorSuggestionsLeader" placeholder="Type leader name or BDC ID" required><div class="form-text">Only Leader BDC IDs are shown.</div></div>
<div class="col-6"><button class="btn btn-primary w-100" name="entry_mode" value="existing">Add Existing</button></div>
<div class="col-6"><button class="btn btn-outline-primary w-100" name="entry_mode" value="create" onclick="return confirm('Create a provisional BDC competitor using only this name? The competitor can complete details later.')">Create Name &amp; Add</button></div>
</form>
<table class="table table-sm align-middle"><thead><tr><th style="width:150px">Bib</th><th>Competitor</th><th>BDC ID</th><th style="width:100px"></th></tr></thead><tbody><?php foreach($entries['leader'] as $x):?><tr><td><form method="post" class="d-flex gap-1"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="update_bib"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="entry_id" value="<?=$x['id']?>"><input class="form-control form-control-sm" style="width:76px" type="number" min="1" name="bib_number" value="<?=$x['bib_number']?>" aria-label="Edit leader bib"><button class="btn btn-sm btn-outline-primary">Save</button></form></td><td><?=e($x['display_name'])?><?php if($x['competitor_status']==='pending'):?> <span class="badge text-bg-warning">Details pending</span><?php endif;?></td><td><code><?=e($x['bdc_id'])?></code></td><td><form method="post"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="remove_entry"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="entry_id" value="<?=$x['id']?>"><button class="btn btn-sm btn-outline-danger">Remove</button></form></td></tr><?php endforeach;?></tbody></table>
</div></div></div>
<div class="col-lg-6"><div class="card shadow-sm role-card"><div class="card-header bg-danger-subtle fw-semibold">Followers</div><div class="card-body">
<form method="post" class="row g-2 mb-3"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="add_entry"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="dance_role" value="follower">
<div class="col-3"><input class="form-control" type="number" min="1" name="bib_number" value="<?=$nextBib['follower']?>" aria-label="Follower bib number" required><div class="form-text">Next suggested bib. You can overwrite it.</div></div>
<div class="col-9"><input class="form-control" name="competitor_search" list="competitorSuggestionsFollower" placeholder="Type follower name or BDC ID" required><div class="form-text">Only Follower BDC IDs are shown.</div></div>
<div class="col-6"><button class="btn btn-danger w-100" name="entry_mode" value="existing">Add Existing</button></div>
<div class="col-6"><button class="btn btn-outline-danger w-100" name="entry_mode" value="create" onclick="return confirm('Create a provisional BDC competitor using only this name? The competitor can complete details later.')">Create Name &amp; Add</button></div>
</form>
<table class="table table-sm align-middle"><thead><tr><th style="width:150px">Bib</th><th>Competitor</th><th>BDC ID</th><th style="width:100px"></th></tr></thead><tbody><?php foreach($entries['follower'] as $x):?><tr><td><form method="post" class="d-flex gap-1"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="update_bib"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="entry_id" value="<?=$x['id']?>"><input class="form-control form-control-sm" style="width:76px" type="number" min="1" name="bib_number" value="<?=$x['bib_number']?>" aria-label="Edit follower bib"><button class="btn btn-sm btn-outline-primary">Save</button></form></td><td><?=e($x['display_name'])?><?php if($x['competitor_status']==='pending'):?> <span class="badge text-bg-warning">Details pending</span><?php endif;?></td><td><code><?=e($x['bdc_id'])?></code></td><td><form method="post"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="remove_entry"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="entry_id" value="<?=$x['id']?>"><button class="btn btn-sm btn-outline-danger">Remove</button></form></td></tr><?php endforeach;?></tbody></table>
</div></div></div>
</div></div></div>
</fieldset>
<div class="card shadow-sm mb-4 <?=$rosterSubmitted?'border-success bg-success-subtle':'border-warning bg-warning-subtle'?>"><div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap"><div><h2 class="h5 mb-1">Competitor Checkpoint</h2><div class="small text-body-secondary"><?=$rosterSubmitted?'Competitors are submitted and locked. Manual score entry is open.':'Save the roster as a draft, then submit and lock it before manual scoring.'?></div><?php if(!empty($rosterState['saved_at'])):?><div class="small mt-1">Last saved: <?=e((string)$rosterState['saved_at'])?></div><?php endif;?></div><div><?php if(!$rosterSubmitted):?><form method="post" class="d-flex gap-2"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><button class="btn btn-outline-dark" name="action" value="save_competitors">Save Competitors</button><button class="btn btn-success" name="action" value="submit_competitors" onclick="return confirm('Submit and lock this competitor roster? Bibs and competitors cannot be changed until an authorised reopen.')">Submit Competitors</button></form><?php elseif(Auth::isSuperAdmin()):?><form method="post" class="d-flex gap-2 flex-wrap"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input class="form-control" name="reopen_reason" placeholder="Required reopen reason" required><button class="btn btn-warning" name="action" value="reopen_competitors" onclick="return confirm('CAUTION: Reopen this locked roster? Manual scoring will be blocked until competitors are submitted again.')">Reopen Competitors</button></form><?php endif;?></div></div></div>
<?php if(!$rosterSubmitted && $judges && ($entries['leader']||$entries['follower'])):?><div class="alert alert-warning"><strong>Manual scoring locked.</strong> Submit Competitors above to open score entry. Tiering is calculated automatically from the larger Leader or Follower count.</div><?php endif;?>
<?php $allRolesDirect=$round['round_type']==='heats'&&($roleAdvancementPlan['leader']['direct_to_final']??false)&&($roleAdvancementPlan['follower']['direct_to_final']??false);if($rosterSubmitted&&$allRolesDirect):?>
<div class="card shadow-sm mb-4 border-success"><div class="card-body"><h2 class="h5 text-success">Heats are not required</h2><p>All <?=count($entries['leader'])?> Leaders and <?=count($entries['follower'])?> Followers fit within the Tier <?=$currentTier?> callback quota. Open Final directly without entering Heats marks.</p><form method="post" class="row g-2 align-items-end"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="advance_roster_direct_final"><input type="hidden" name="round_id" value="<?=$roundId?>"><div class="col-md-4"><label class="form-label">Final date</label><input class="form-control" type="date" name="next_schedule_date" value="<?=e(date('Y-m-d'))?>" required></div><div class="col-md-2"><label class="form-label">Hour</label><select class="form-select" name="next_schedule_hour"><?php for($h=1;$h<=12;$h++):?><option><?=$h?></option><?php endfor;?></select></div><div class="col-md-2"><label class="form-label">Minute</label><select class="form-select" name="next_schedule_minute"><?php for($m=0;$m<60;$m+=5):?><option value="<?=$m?>"><?=str_pad((string)$m,2,'0',STR_PAD_LEFT)?></option><?php endfor;?></select></div><div class="col-md-2"><label class="form-label">AM / PM</label><select class="form-select" name="next_schedule_period"><option>AM</option><option>PM</option></select></div><div class="col-md-2"><button class="btn btn-success w-100" onclick="return confirm('Skip Heats and open Final with every submitted competitor?')">Go Directly to Final</button></div></form></div></div>
<?php endif;?>
<?php if($rosterSubmitted&&!$allRolesDirect):$directRoleLabels=[];foreach(['leader'=>'Leaders','follower'=>'Followers'] as $directRole=>$directLabel)if($roleAdvancementPlan[$directRole]['direct_to_final']??false)$directRoleLabels[]='All '.count($entries[$directRole]).' '.$directLabel.' advance directly to Final';if($directRoleLabels):?><div class="alert alert-success"><strong>Role-specific direct Final:</strong> <?=e(implode('. ',$directRoleLabels))?>. Only the larger role appears on the Heats score sheet.</div><?php endif;endif;?>
<?php if($judges && ($entries['leader']||$entries['follower'])&&!$allRolesDirect):$leaderPanelCount=count(array_filter($judges,fn($judge)=>in_array($judge['scoring_scope']??'all',['all','leader'],true)));$followerPanelCount=count(array_filter($judges,fn($judge)=>in_array($judge['scoring_scope']??'all',['all','follower'],true)));?><div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"><div><span class="badge text-bg-primary me-2">Leader Panel: <?=$leaderPanelCount?> judges</span><span class="badge text-bg-danger">Follower Panel: <?=$followerPanelCount?> judges</span></div><a class="btn btn-outline-dark btn-sm" href="#judge-setup">Reselect Judges</a></div><form method="post" id="heatsScoreForm" data-callback-count="<?=(int)$round['callback_count']?>"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="score_payload" id="scorePayload" value=""><div class="card shadow-sm mb-4"><div class="card-body"><h2 class="h5">Manual <?=e(ucfirst($round['round_type']))?> Score Entry</h2><div class="text-muted small mb-2">Enter 1 or YES for a YES mark. Enter A1, A2 or A3 for alternates. Every save retains the screen data.</div><div class="alert alert-info py-2 mb-3"><strong>Live Preview:</strong> totals and provisional ranks update immediately. Use Calculate &amp; Sort to review the server result before Submit Scores.</div><?php foreach(['leader'=>'Leaders','follower'=>'Followers'] as $role=>$label):if($roleAdvancementPlan[$role]['direct_to_final']??false)continue;?><h3 class="h6 mt-4"><?=$label?></h3><div class="table-responsive"><table class="table table-bordered score-table"><thead><tr><th>Bib</th><th>Name</th><?php foreach($judges as $j):?><th><?=e($j['judge_name'])?><?=(int)$j['is_chief']?' ★':''?></th><?php endforeach;?><th>Total</th><th>Result</th></tr></thead><tbody><?php foreach($entries[$role] as $x):$res=$results[$x['id']]??null;?><tr class="score-row <?=e($res['result_status']??'')?>" data-role="<?=$role?>" data-entry-id="<?=$x['id']?>"><td><?=$x['bib_number']?></td><td><?=e($x['display_name'])?></td><?php foreach($judges as $j):$assigned=in_array($j['scoring_scope']??'all',['all',$role],true);$m=$marks[$x['id']][$j['id']]??null;$val='';if($m){$val=$m['mark_type']==='yes'?'1':($m['mark_type']==='alt'?'A'.$m['alt_rank']:'');}?><td><?php if($assigned):?><input class="form-control form-control-sm score-input" data-entry-id="<?=$x['id']?>" data-judge-id="<?=$j['id']?>" data-chief="<?=(int)$j['is_chief']?>" name="mark[<?=$x['id']?>][<?=$j['id']?>]" value="<?=e($val)?>"><?php else:?><span class="badge text-bg-light border text-muted">Not Assigned</span><?php endif;?></td><?php endforeach;?><td><span class="live-total"><?=isset($res['total_score'])?number_format((float)$res['total_score'],1):'0.0'?></span><div class="small text-muted">YES <span class="live-yes">0</span> · Chief <span class="live-chief">0.0</span></div></td><td><span class="live-status"><?=e($res['result_status']??'Live preview')?></span> <span class="live-rank"><?=isset($res['rank_number'])?'#'.$res['rank_number']:''?></span></td></tr><?php endforeach;?></tbody></table></div><?php endforeach;?></div></div>
<div class="card shadow-sm mb-3"><div class="card-body">
 <h2 class="h6 mb-3">Scoring Witnesses</h2>
 <div class="row g-2">
  <div class="col-md-3"><label class="form-label">Witness 1</label><input class="form-control" name="witness_1" maxlength="190" value="<?=e((string)($round['witness_1']??''))?>" placeholder="Witness name"></div>
  <div class="col-md-3"><label class="form-label">Witness 2</label><input class="form-control" name="witness_2" maxlength="190" value="<?=e((string)($round['witness_2']??''))?>" placeholder="Witness name"></div>
  <div class="col-md-3"><label class="form-label">Witness 3</label><input class="form-control" name="witness_3" maxlength="190" value="<?=e((string)($round['witness_3']??''))?>" placeholder="Witness name"></div>
  <div class="col-md-3"><label class="form-label">Scoring Administrator</label><input class="form-control" name="scoring_administrator" maxlength="190" value="<?=e((string)($round['scoring_administrator']??''))?>" placeholder="Administrator name"></div>
 </div>
 <div class="form-text mt-2">Witness names are saved with the scoring draft and printed on the official draft result.</div>
</div></div>
<div class="sticky-actions d-flex flex-wrap gap-2 align-items-center">
 <button name="action" value="save_scores" class="btn btn-outline-dark">Save Draft</button>
 <button name="action" value="calculate_scores" class="btn btn-warning">Calculate &amp; Sort</button>
 <button name="action" value="submit_scores" class="btn btn-primary">Submit Scores</button>
 <span id="autosaveStatus" class="small text-muted ms-auto">Autosave ready</span>
 <?php if(in_array($round['status'],['awaiting_decision','scores_submitted'],true)):?>
 <a class="btn btn-outline-primary" target="_blank" href="result.php?round_id=<?=$roundId?>">Preview / Print Draft Result</a>
 <?php endif;?>
</div></form>

<script src="round-schedule-picker.js?v=187"></script>
<?php if(in_array($round['status'],['awaiting_decision','scores_submitted'],true)):?>
<div class="card shadow-sm mt-3 mb-4 border-primary">
 <div class="card-body">
  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
   <div>
    <h2 class="h5 mb-1">Next Round</h2>
    <p class="text-muted mb-0">Scores are submitted and sorted. Choose the next round for the callback competitors.</p>
   </div>
   <a class="btn btn-outline-primary" target="_blank" href="result.php?round_id=<?=$roundId?>">Print Full Draft Result</a>
  </div>

  <?php if($tieGroups):?>
   <div class="alert alert-warning mt-3 mb-0">Resolve all callback ties above before creating the next round.</div>
  <?php else:?>
   <div class="d-flex flex-wrap gap-2 mt-3">
    <?php if($round['round_type']==='heats'):?>
     <form method="post">
      <input type="hidden" name="_csrf" value="<?=e($csrf)?>">
      <input type="hidden" name="action" value="create_next_round">
      <input type="hidden" name="round_id" value="<?=$roundId?>">
      <input type="hidden" name="next_round_type" value="semifinal">
      <div class="border rounded p-2 mb-2" style="min-width:340px"><div class="small fw-bold mb-2">Next-round schedule</div><div class="row g-2"><div class="col-12"><label class="form-label small mb-1">Date</label><input class="form-control form-control-sm" type="date" name="next_schedule_date" value="<?=e(date('Y-m-d'))?>" required></div><div class="col-4"><label class="form-label small mb-1">Hour</label><select class="form-select form-select-sm" name="next_schedule_hour" required><?php for($scheduleHour=1;$scheduleHour<=12;$scheduleHour++):?><option value="<?=$scheduleHour?>"><?=$scheduleHour?></option><?php endfor;?></select></div><div class="col-4"><label class="form-label small mb-1">Minute</label><select class="form-select form-select-sm" name="next_schedule_minute" required><?php for($scheduleMinute=0;$scheduleMinute<60;$scheduleMinute+=5):$scheduleMinuteLabel=str_pad((string)$scheduleMinute,2,'0',STR_PAD_LEFT);?><option value="<?=$scheduleMinute?>"><?=$scheduleMinuteLabel?></option><?php endfor;?></select></div><div class="col-4"><label class="form-label small mb-1">AM / PM</label><select class="form-select form-select-sm" name="next_schedule_period" required><option value="AM">AM</option><option value="PM">PM</option></select></div></div></div>
      <button class="btn btn-warning">Move Callbacks to Semifinal</button>
     </form>
     <form method="post">
      <input type="hidden" name="_csrf" value="<?=e($csrf)?>">
      <input type="hidden" name="action" value="create_next_round">
      <input type="hidden" name="round_id" value="<?=$roundId?>">
      <input type="hidden" name="next_round_type" value="final">
      <div class="border rounded p-2 mb-2" style="min-width:340px"><div class="small fw-bold mb-2">Next-round schedule</div><div class="row g-2"><div class="col-12"><label class="form-label small mb-1">Date</label><input class="form-control form-control-sm" type="date" name="next_schedule_date" value="<?=e(date('Y-m-d'))?>" required></div><div class="col-4"><label class="form-label small mb-1">Hour</label><select class="form-select form-select-sm" name="next_schedule_hour" required><?php for($scheduleHour=1;$scheduleHour<=12;$scheduleHour++):?><option value="<?=$scheduleHour?>"><?=$scheduleHour?></option><?php endfor;?></select></div><div class="col-4"><label class="form-label small mb-1">Minute</label><select class="form-select form-select-sm" name="next_schedule_minute" required><?php for($scheduleMinute=0;$scheduleMinute<60;$scheduleMinute+=5):$scheduleMinuteLabel=str_pad((string)$scheduleMinute,2,'0',STR_PAD_LEFT);?><option value="<?=$scheduleMinute?>"><?=$scheduleMinuteLabel?></option><?php endfor;?></select></div><div class="col-4"><label class="form-label small mb-1">AM / PM</label><select class="form-select form-select-sm" name="next_schedule_period" required><option value="AM">AM</option><option value="PM">PM</option></select></div></div></div>
      <button class="btn btn-dark">Move Callbacks Directly to Final</button>
     </form>
    <?php elseif($round['round_type']==='semifinal'):?>
     <form method="post">
      <input type="hidden" name="_csrf" value="<?=e($csrf)?>">
      <input type="hidden" name="action" value="create_next_round">
      <input type="hidden" name="round_id" value="<?=$roundId?>">
      <input type="hidden" name="next_round_type" value="final">
      <div class="border rounded p-2 mb-2" style="min-width:340px"><div class="small fw-bold mb-2">Next-round schedule</div><div class="row g-2"><div class="col-12"><label class="form-label small mb-1">Date</label><input class="form-control form-control-sm" type="date" name="next_schedule_date" value="<?=e(date('Y-m-d'))?>" required></div><div class="col-4"><label class="form-label small mb-1">Hour</label><select class="form-select form-select-sm" name="next_schedule_hour" required><?php for($scheduleHour=1;$scheduleHour<=12;$scheduleHour++):?><option value="<?=$scheduleHour?>"><?=$scheduleHour?></option><?php endfor;?></select></div><div class="col-4"><label class="form-label small mb-1">Minute</label><select class="form-select form-select-sm" name="next_schedule_minute" required><?php for($scheduleMinute=0;$scheduleMinute<60;$scheduleMinute+=5):$scheduleMinuteLabel=str_pad((string)$scheduleMinute,2,'0',STR_PAD_LEFT);?><option value="<?=$scheduleMinute?>"><?=$scheduleMinuteLabel?></option><?php endfor;?></select></div><div class="col-4"><label class="form-label small mb-1">AM / PM</label><select class="form-select form-select-sm" name="next_schedule_period" required><option value="AM">AM</option><option value="PM">PM</option></select></div></div></div>
      <button class="btn btn-dark">Move Semifinal Callbacks to Final</button>
     </form>
    <?php endif;?>

    <?php if((int)$round['parent_round_id']>0):?>
     <form method="post" onsubmit="return confirm('Cancel this round draft and return to the previous round?');">
      <input type="hidden" name="_csrf" value="<?=e($csrf)?>">
      <input type="hidden" name="action" value="cancel_child_round">
      <input type="hidden" name="round_id" value="<?=$roundId?>">
      <button class="btn btn-outline-danger">Cancel This Round &amp; Go Back</button>
     </form>
    <?php endif;?>
   </div>
  <?php endif;?>
 </div>
</div>
<?php endif;?>

<?php
$tiePanelTest=false;
$tiePanelAction='';
$tiePanelAttributes='';
require dirname(__DIR__).'/scoring/tie-resolution-panel.php';
?>

<?php endif;?>
<?php endif;?><?php endif;?>
<?php if($round && !(($round['round_type']??'')==='final' && ($round['scoring_mode']??'manual')==='automated')):$backupTestMode=false;require __DIR__.'/backup-panel.php';endif;?>
</div><script>
function confirmDeleteWorkflow(form,eventName,division){
 const answer=window.prompt(
  'Delete ALL '+division+' scoring rounds for '+eventName+'?\n\n'
  +'This removes Heats, Semifinal, Final, judges, marks, pairings and calculated results.\n'
  +'The event and competitor records remain.\n\n'
  +'Type DELETE to continue.'
 );
 if(answer!=='DELETE')return false;
 form.querySelector('[name="delete_confirmation"]').value='DELETE';
 return window.confirm('Final confirmation: permanently delete this complete test scoring workflow?');
}
function updateTierSummary(){const tier=document.getElementById('competitionTier');const out=document.getElementById('tierYesCount');if(!tier||!out)return;out.value=({1:5,2:10,3:15})[tier.value]||10;}let finalJudgeSequence=1000;
function renumberFinalJudges(){
 const rows=document.querySelectorAll('#finalJudgesWrap [data-judge-row]');
 rows.forEach((row,index)=>{
  const label=row.querySelector('.final-judge-number');
  if(label)label.textContent='Judge '+(index+1);
 });
}
function addFinalJudge(){
 const w=document.getElementById('finalJudgesWrap');
 if(!w)return;
 const key='new_'+(finalJudgeSequence++);
 const d=document.createElement('div');
 d.className='input-group mb-2 judge-row';
 d.setAttribute('data-judge-row','');
 d.innerHTML='<span class="input-group-text final-judge-number"></span>'
  +'<input type="hidden" name="final_judges['+key+'][id]" value="0">'
  +'<input type="hidden" name="final_judges['+key+'][directory_id]" value="0">'
  +'<input class="form-control" list="judgeDirectorySuggestions" name="final_judges['+key+'][name]" placeholder="Search or type a new judge" required>'
  +'<span class="input-group-text"><input type="radio" name="final_chief_key" value="'+key+'"> Chief</span>'
  +'<button type="button" class="btn btn-outline-danger" onclick="removeFinalJudge(this)">Remove</button>';
 w.appendChild(d);
 renumberFinalJudges();
}
function removeFinalJudge(button){
 const row=button.closest('[data-judge-row]');
 if(!row)return;
 const rows=document.querySelectorAll('#finalJudgesWrap [data-judge-row]');
 if(rows.length<=3){alert('Final requires at least 3 judges.');return;}
 row.remove();
 renumberFinalJudges();
}function addJudge(){const w=document.getElementById('judgesWrap');const i=w.querySelectorAll('.judge-row').length;const d=document.createElement('div');d.className='row g-2 mb-2 judge-row align-items-center';d.innerHTML='<div class="col-md-2"><strong>Judge '+(i+1)+'</strong><input type="hidden" name="judge_assignment_id[]" value="0"><input type="hidden" name="judge_directory_id[]" value="0"></div><div class="col-md-5"><input class="form-control" name="judge_name[]" list="judgeDirectorySuggestions" placeholder="Search or type a new judge" required></div><div class="col-md-3"><select class="form-select" name="judge_scope[]"><option value="all">All</option><option value="leader">Leaders</option><option value="follower">Followers</option></select></div><div class="col-md-2"><label><input type="radio" name="chief_index" value="'+i+'"> Chief</label></div>';w.appendChild(d);}
const scoreForm=document.getElementById('heatsScoreForm');
const autosaveStatus=document.getElementById('autosaveStatus');
const scoreScrollKey='bdc-score-scroll:'+location.pathname+':<?=$roundId?>';
function rememberScoreScroll(action){
 if(!['save_scores','calculate_scores','submit_scores'].includes(action))return;
 try{sessionStorage.setItem(scoreScrollKey,JSON.stringify({y:Math.max(0,window.scrollY),at:Date.now()}));}catch(error){}
}
function restoreScoreScroll(){
 let saved=null;try{saved=JSON.parse(sessionStorage.getItem(scoreScrollKey)||'null');sessionStorage.removeItem(scoreScrollKey);}catch(error){}
 if(!saved||!Number.isFinite(saved.y)||Date.now()-Number(saved.at||0)>120000)return;
 if('scrollRestoration' in history)history.scrollRestoration='manual';
 requestAnimationFrame(()=>requestAnimationFrame(()=>setTimeout(()=>window.scrollTo({top:saved.y,left:0,behavior:'auto'}),40)));
}
restoreScoreScroll();
const scoreWeights={'1':10,'YES':10,'Y':10,'A1':4.5,'A2':4.3,'A3':4.2,'2':4.5,'3':4.3,'4':4.2,'':0};
let autosaveTimers=new Map();
let unsavedScoreChanges=false;
function normaliseScoreValue(raw){const value=String(raw||'').trim().toUpperCase();if(value==='YES'||value==='Y'||value==='1')return '1';if(value==='A1'||value==='2')return 'A1';if(value==='A2'||value==='3')return 'A2';if(value==='A3'||value==='4')return 'A3';return '';}
function weightFor(raw){return scoreWeights[normaliseScoreValue(raw)]||0;}
function updateLiveScoring(){
 if(!scoreForm)return;
 const callbackCount=parseInt(scoreForm.dataset.callbackCount||'0',10);
 ['leader','follower'].forEach(role=>{
  const rows=[...scoreForm.querySelectorAll('.score-row[data-role="'+role+'"]')];
  const calculated=rows.map(row=>{
   let total=0,yes=0,chief=0;
   row.querySelectorAll('.score-input').forEach(input=>{const value=normaliseScoreValue(input.value);const weight=weightFor(value);total+=weight;if(value==='1')yes++;if(input.dataset.chief==='1')chief=weight;});
   row.querySelector('.live-total').textContent=total.toFixed(1);
   row.querySelector('.live-yes').textContent=String(yes);
   row.querySelector('.live-chief').textContent=chief.toFixed(1);
   return {row,total,chief,yes,entry:parseInt(row.dataset.entryId||'0',10)};
  });
  calculated.sort((a,b)=>b.total-a.total||a.entry-b.entry);
  const alternateLimit=Math.min(callbackCount+3,calculated.length);
  for(let start=0;start<calculated.length;){
   let end=start;while(end+1<calculated.length&&calculated[end+1].total===calculated[start].total)end++;
   const startPosition=start+1,endPosition=end+1,rank=startPosition;
   const crossesCallback=startPosition<=callbackCount&&endPosition>callbackCount;
   const crossesAlternate=startPosition<=alternateLimit&&endPosition>alternateLimit;
   const tiedAlternate=end>start&&startPosition>callbackCount&&endPosition<=alternateLimit;
   let status='Eliminated',rowClass='';
   if(crossesCallback||crossesAlternate||tiedAlternate){status='Tie Pending';rowClass='tie_pending';}
   else if(endPosition<=callbackCount){status='Callback';rowClass='callback';}
   else if(startPosition>callbackCount&&endPosition<=alternateLimit){status='Alternate';rowClass='alternate';}
   for(let index=start;index<=end;index++){
    const item=calculated[index];item.row.classList.remove('callback','alternate','tie_pending');if(rowClass)item.row.classList.add(rowClass);
    item.row.querySelector('.live-rank').textContent='#'+rank;item.row.querySelector('.live-status').textContent=status+' · Live';
   }
   start=end+1;
  }
 });
}
function setAutosaveStatus(text,className){if(!autosaveStatus)return;autosaveStatus.textContent=text;autosaveStatus.className='small ms-auto '+(className||'text-muted');}
async function autosaveScore(input){
 const key=input.dataset.entryId+':'+input.dataset.judgeId;
 if(autosaveTimers.has(key))clearTimeout(autosaveTimers.get(key));
 autosaveTimers.set(key,setTimeout(async()=>{
  setAutosaveStatus('Saving…','text-primary');
  try{
   const body=new URLSearchParams();
   body.set('_csrf',scoreForm.querySelector('[name="_csrf"]').value);
   body.set('round_id',scoreForm.querySelector('[name="round_id"]').value);
   body.set('entry_id',input.dataset.entryId);
   body.set('judge_id',input.dataset.judgeId);
   body.set('value',normaliseScoreValue(input.value));
   const response=await fetch('autosave.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},body:body.toString()});
   const data=await response.json();
   if(!response.ok||!data.ok)throw new Error(data.error||'Autosave failed');
   unsavedScoreChanges=false;
   setAutosaveStatus('Saved at '+new Date().toLocaleTimeString(),'text-success');
  }catch(error){unsavedScoreChanges=true;setAutosaveStatus('Autosave failed · use Save Draft','text-danger');}
 },900));
}
function buildScorePayload(){const payload={};scoreForm.querySelectorAll('.score-input').forEach(input=>{const entry=input.dataset.entryId,judge=input.dataset.judgeId;if(!payload[entry])payload[entry]={};payload[entry][judge]=normaliseScoreValue(input.value);});return payload;}
if(scoreForm){
 const handleScoreChange=input=>{
  if(!input||!input.classList.contains('score-input'))return;
  input.value=normaliseScoreValue(input.value);
  unsavedScoreChanges=true;
  setAutosaveStatus('Unsaved changes','text-warning');
  updateLiveScoring();
  autosaveScore(input);
 };
 scoreForm.addEventListener('input',event=>handleScoreChange(event.target));
 scoreForm.addEventListener('change',event=>handleScoreChange(event.target));
 scoreForm.addEventListener('keyup',event=>{
  if(event.target&&event.target.classList.contains('score-input'))updateLiveScoring();
 });
 scoreForm.addEventListener('submit',event=>{const action=event.submitter?.value||'';rememberScoreScroll(action);if(['calculate_scores','submit_scores'].includes(action))showScoringProgress();
  document.getElementById('scorePayload').value=JSON.stringify(buildScorePayload());
  scoreForm.querySelectorAll('.score-input').forEach(input=>input.removeAttribute('name'));
  setAutosaveStatus('Saving full draft…','text-primary');
 });
 requestAnimationFrame(updateLiveScoring);
}
window.addEventListener('beforeunload',event=>{if(!unsavedScoreChanges)return;event.preventDefault();event.returnValue='';});

const scoringOverlay=document.getElementById('scoringProgressOverlay');
const scoringProgressText=document.getElementById('scoringProgressText');
const scoringProgressBar=document.getElementById('scoringProgressBar');
function showScoringProgress(){
 if(!scoringOverlay)return;
 scoringOverlay.style.display='flex';
 const stages=[['Saving scores…',20],['Calculating totals…',42],['Sorting competitors…',64],['Resolving ties…',82],['Rendering results…',94]];
 let index=0;
 const advance=()=>{if(index>=stages.length)return;scoringProgressText.textContent=stages[index][0];scoringProgressBar.style.width=stages[index][1]+'%';index++;setTimeout(advance,650);};
 advance();
}

const finalScoreForm=document.getElementById('finalScoreForm');
if(finalScoreForm){
 finalScoreForm.addEventListener('submit',event=>{
  const submitter=event.submitter;
  if(submitter&&submitter.value==='generate_test_final_scores')return;

  const payload={};
  const byJudge={};
  let invalidMessage='';
  finalScoreForm.querySelectorAll('.final-rank-input').forEach(input=>{
   const pair=input.dataset.pairId;
   const judge=input.dataset.judgeId;
   const rank=input.value===''?'':parseInt(input.value,10);
   if(!payload[pair])payload[pair]={};
   payload[pair][judge]=rank;
   if(!byJudge[judge])byJudge[judge]=[];
   byJudge[judge].push(rank===''?0:rank);
  });

  const pairCount=<?=isset($finalRankCount)?(int)$finalRankCount:0?>;
  Object.entries(byJudge).some(([judge,ranks])=>{
   ranks=ranks.filter(rank=>rank>0);
   if(ranks.length!==pairCount||ranks.some(rank=>rank<1||rank>pairCount)){
    invalidMessage='Every Final judge must assign exactly the Top '+pairCount+' ranks.';
    return true;
   }
   if(new Set(ranks).size!==pairCount){
    invalidMessage='Each Final judge must use every rank exactly once. Duplicate ranks were found.';
    return true;
   }
   return false;
  });

  if(invalidMessage){
   event.preventDefault();
   alert(invalidMessage);
   return;
  }

  document.getElementById('finalRankPayload').value=JSON.stringify(payload);
  finalScoreForm.querySelectorAll('.final-rank-input').forEach(input=>input.removeAttribute('name'));
  if(typeof showScoringProgress==='function')showScoringProgress();
 });
}
if(finalScoreForm)finalScoreForm.querySelectorAll('.final-rank-input').forEach(input=>input.addEventListener('change',()=>{if(input.value==='')return;const duplicate=[...finalScoreForm.querySelectorAll('.final-rank-input')].find(other=>other!==input&&other.dataset.judgeId===input.dataset.judgeId&&other.value===input.value);if(duplicate){alert('Rank '+input.value+' is already assigned for this judge. Choose another rank or clear the existing one.');input.value='';input.focus();}}));

document.querySelectorAll('.final-judge-page-button').forEach(button=>{
 button.addEventListener('click',()=>{
  const page=button.dataset.page;
  document.querySelectorAll('.final-judge-column').forEach(cell=>{cell.style.display=cell.dataset.judgePage===page?'':'none';});
  document.querySelectorAll('.final-judge-page-button').forEach(item=>{
   item.classList.toggle('btn-dark',item===button);
   item.classList.toggle('btn-outline-dark',item!==button);
  });
 });
});

async function refreshRegistrationDeskSync(){
 const box=document.getElementById('deskSyncStats');
 if(!box)return;
 try{
  const response=await fetch('registration-sync.php?round_id=<?=$roundId?>',{headers:{'X-Requested-With':'XMLHttpRequest'}});
  const data=await response.json();
  if(!data.ok)return;
  box.querySelector('[data-stat="leaders"]').textContent=data.leaders_ready+' / '+data.leaders_total;
  box.querySelector('[data-stat="followers"]').textContent=data.followers_ready+' / '+data.followers_total;
  box.querySelector('[data-stat="missing"]').textContent=data.missing_bibs;
  box.querySelector('[data-stat="updated"]').textContent=data.last_update||'No desk changes yet';
 }catch(error){}
}
refreshRegistrationDeskSync();
setInterval(refreshRegistrationDeskSync,3000);
</script><script src="../../public/js/final-pairing-sync.js?v=386" defer></script><script src="../../public/js/final-score-sync.js?v=386" defer></script><script src="../../public/js/bdc-copy-link-v345.js?v=345"></script><script src="../../public/js/judge-order-controls.js?v=380"></script><script src="../../public/js/scoring-judge-directory.js?v=381"></script></body></html>
