Warning: truncated output (original token count: 46441)
Total output lines: 2266

<?php
declare(strict_types=1);
ob_start(static fn(string $html):string=>str_replace('</head>','<script defer src="../../public/assets/js/bdc-theme.js?v=505"></script></head>',$html));

// Return plain HTML from PHP. Bluehost was caching an already-compressed body
// and then applying transport gzip again, leaving binary bytes in the browser.
// Never force Content-Encoding: identity; nginx must advertise its real encoding.
if (function_exists('ini_set')) {
    @ini_set('zlib.output_compression', '0');
    @ini_set('output_handler', '');
}
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-transform, no-store, private');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Accel-Expires: 0');

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\SchemaUpdater;
use App\Services\ResultStorageService;
use App\Services\NextRankedFinalistService;
use App\Services\SpecialCategoryService;
use App\Services\TestAutomaticJudgeService;
use App\Services\ScoringBackupService;
use App\Services\ScoringRosterCheckpointService;
use App\Services\RoleAdvancementService;

Auth::requireAdmin();
$pdo=Database::connection();
\App\Services\ScoringPageGuardService::prepare($pdo,true);
set_exception_handler(static function(\Throwable $exception):void{\App\Services\ScoringPageGuardService::renderFailure($exception,true);});

$userId=(int)(Auth::user()['id']??0);
$testMode=(string)($_GET['test_mode']??$_POST['test_mode']??$_SESSION['bdc_test_scoring_mode']??'manual');
if(!in_array($testMode,['manual','automated'],true))$testMode='manual';
$_SESSION['bdc_test_scoring_mode']=$testMode;
$error=''; $notice='';


function testRandomRows(PDO $pdo,string $role,int $count):array{
 $count=max(0,min(500,$count));
 if($count===0)return [];
 $stmt=$pdo->prepare("
  SELECT *
  FROM bdc_competitors
  WHERE status='active'
    AND dance_role=:role
  ORDER BY RAND()
  LIMIT {$count}
 ");
 $stmt->execute(['role'=>$role]);
 return $stmt->fetchAll();
}

function copyOfficialCompetitorToTest(PDO $pdo,array $competitor):void{
 $columns=array_keys($competitor);
 $quoted=array_map(fn(string $column):string=>'`'.str_replace('`','',$column).'`',$columns);
 $placeholders=array_map(fn(string $column):string=>':'.$column,$columns);

 $sql="INSERT INTO bdc_test_competitors(".implode(',',$quoted).")
       VALUES(".implode(',',$placeholders).")
       ON DUPLICATE KEY UPDATE exact_name=VALUES(exact_name),dance_role=VALUES(dance_role),country=VALUES(country),status=VALUES(status)";
 $stmt=$pdo->prepare($sql);
 $stmt->execute($competitor);
}

function randomHeatsValue():array{
 $roll=random_int(1,100);
 if($roll<=35)return ['yes',null,10.0];
 if($roll<=55)return ['alt',1,4.5];
 if($roll<=72)return ['alt',2,4.3];
 if($roll<=85)return ['alt',3,4.2];
 return ['blank',null,0.0];
}

function auditScoring(PDO $pdo,int $roundId,int $userId,string $action,array $details=[]):void{
    $s=$pdo->prepare('INSERT INTO bdc_test_scoring_audit(round_id,user_id,action,details_json) VALUES(:r,:u,:a,:d)');
    $s->execute(['r'=>$roundId,'u'=>$userId?:null,'a'=>$action,'d'=>json_encode($details,JSON_UNESCAPED_UNICODE)]);
}
function loadRound(PDO $pdo,int $id):?array{
    $s=$pdo->prepare('SELECT r.*,e.name event_name,e.event_date,e.venue FROM bdc_test_scoring_rounds r JOIN bdc_test_events e ON e.id=r.event_id WHERE r.id=:id');
    $s->execute(['id'=>$id]); return $s->fetch()?:null;
}
function resultRoot():string{
    return ResultStorageService::root();
}
function safeFile(string $value):string{
    $v=preg_replace('/[^A-Za-z0-9_-]+/','-',trim($value)); return trim((string)$v,'-')?:'result';
}
function automaticTierFromRoleCounts(PDO $pdo,int $roundId):array{
    $stmt=$pdo->prepare("SELECT dance_role,COUNT(*) total FROM bdc_test_scoring_entries WHERE round_id=:round AND entry_status='active' GROUP BY dance_role");
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
    $stmt=$pdo->prepare("SELECT tier_manual_override,yes_count,callback_count FROM bdc_test_scoring_rounds WHERE id=:round");
    $stmt->execute(['round'=>$roundId]);
    $stored=$stmt->fetch()?:[];
    $manual=(int)($stored['tier_manual_override']??0)===1;
    $normalized=\App\Services\ScoringRulesService::normalizeNormalRoundTier(
        $info['leaders'],
        $info['followers'],
        (int)($stored['yes_count']??0),
        (int)($stored['callback_count']??0),
        $force?false:$manual
    );
    $pdo->prepare("UPDATE bdc_test_scoring_rounds SET yes_count=:yes_count,callback_count=:callback_count WHERE id=:round")
        ->execute([
            'yes_count'=>$normalized['yes_count'],
            'callback_count'=>$normalized['callback_count'],
            'round'=>$roundId
        ]);
    $info['tier']=$normalized['tier'];
    $info['yes']=$normalized['yes_count'];
    $info['manual']=$manual&&!$force;
    return $info;
}

function computeResults(PDO $pdo,array $round,int $userId):void{
    $rid=(int)$round['id'];
    $roleCountStmt=$pdo->prepare("SELECT dance_role,COUNT(*) total FROM bdc_test_scoring_entries WHERE round_id=:r AND entry_status='active' GROUP BY dance_role");
    $roleCountStmt->execute(['r'=>$rid]);$roleCounts=['leader'=>0,'follower'=>0];foreach($roleCountStmt->fetchAll() as $row)$roleCounts[$row['dance_role']]=(int)$row['total'];
    $rolePlan=RoleAdvancementService::roundPlan($roleCounts['leader'],$roleCounts['follower'],(int)$round['yes_count']);
    $judges=$pdo->prepare('SELECT * FROM bdc_test_scoring_judges WHERE round_id=:r ORDER BY judge_order');$judges->execute(['r'=>$rid]);$judges=$judges->fetchAll();
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
    $entries=$pdo->prepare("SELECT * FROM bdc_test_scoring_entries WHERE round_id=:r AND entry_status='active' ORDER BY dance_role,bib_number");$entries->execute(['r'=>$rid]);$entries=$entries->fetchAll();
    if(!$entries) throw new RuntimeException('Add competitors before calculating.');
    $markStmt=$pdo->prepare('SELECT judge_id,weighted_score FROM bdc_test_scoring_marks WHERE entry_id=:e');
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
        $up=$pdo->prepare("INSERT INTO bdc_test_scoring_results(round_id,entry_id,total_score,chief_score,rank_number,result_status,alternate_rank,generated_version) VALUES(:r,:e,:t,:c,:rank,:st,:alt,:v) ON DUPLICATE KEY UPDATE total_score=VALUES(total_score),chief_score=VALUES(chief_score),rank_number=VALUES(rank_number),result_status=VALUES(result_status),alternate_rank=VALUES(alternate_rank),generated_version=VALUES(generated_version),updated_at=NOW()");
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
        $pdo->prepare("UPDATE bdc_test_scoring_rounds SET status='awaiting_decision',generated_version=:v WHERE id=:id")->execute(['v'=>$version,'id'=>$rid]);
        auditScoring($pdo,$rid,$userId,'results_generated',['version'=>$version]);
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}



function calculateRelativePlacement(PDO $pdo,int $roundId,int $userId):array{
    $limitStmt=$pdo->prepare("SELECT callback_count FROM bdc_test_scoring_rounds WHERE id=:r");$limitStmt->execute(['r'=>$roundId]);$rankLimit=max(1,(int)$limitStmt->fetchColumn());
    $judgeStmt=$pdo->prepare("SELECT id,judge_order,is_chief,judge_name FROM bdc_test_scoring_judges WHERE round_id=:r ORDER BY judge_order");
    $judgeStmt->execute(['r'=>$roundId]);
    $judges=$judgeStmt->fetchAll();
    if(count($judges)<3) throw new RuntimeException('At least 3 judges are required for Final scoring.');

    $pairStmt=$pdo->prepare("SELECT * FROM bdc_test_scoring_final_pairs WHERE round_id=:r AND pairing_status='confirmed' ORDER BY pair_number");
    $pairStmt->execute(['r'=>$roundId]);
    $pairs=$pairStmt->fetchAll();
    if(!$pairs) throw new RuntimeException('Confirm Final pairing before calculating rankings.');

    $markStmt=$pdo->prepare("SELECT pair_id,judge_id,rank_value FROM bdc_test_scoring_final_marks WHERE round_id=:r");
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
        $pdo->prepare("DELETE FROM bdc_test_scoring_final_results WHERE round_id=:r")->execute(['r'=>$roundId]);
        $insert=$pdo->prepare("
          INSERT INTO bdc_test_scoring_final_results(
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
      FROM bdc_test_scoring_entries se
      JOIN bdc_test_scoring_results sr
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
      FROM bdc_test_scoring_entries child
      WHERE child.round_id=:child_round
        AND child.entry_status='active'
        AND EXISTS(
          SELECT 1 FROM bdc_test_scoring_entries source_entry
          WHERE source_entry.round_id=:source_round
            AND source_entry.competitor_id=child.competitor_id
            AND source_entry.dance_role=child.dance_role
        )
        AND NOT EXISTS(
          SELECT 1
          FROM bdc_test_scoring_entries source_entry
          JOIN bdc_test_scoring_results source_result
            ON source_result.entry_id=source_entry.id
           AND source_result.round_id=source_entry.round_id
          WHERE source_entry.round_id=:source_round_callback
            AND source_entry.competitor_id=child.competitor_id
            AND source_entry.dance_role=child.dance_role
            AND source_entry.entry_status='active'
            AND source_result.result_status='callback'
        )
        AND NOT EXISTS(
          SELECT 1 FROM bdc_test_scoring_audit manual_audit
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
      $pairStmt=$pdo->prepare("SELECT id FROM bdc_test_scoring_final_pairs WHERE round_id=? AND (leader_entry_id IN ($stalePlaceholders) OR follower_entry_id IN ($stalePlaceholders))");
      $pairStmt->execute(array_merge([$childRoundId],$staleIds,$staleIds));
      $stalePairIds=array_map('intval',$pairStmt->fetchAll(PDO::FETCH_COLUMN));
      if($stalePairIds){
        $pairPlaceholders=implode(',',array_fill(0,count($stalePairIds),'?'));
        $pdo->prepare("DELETE FROM bdc_test_scoring_final_marks WHERE pair_id IN ($pairPlaceholders)")->execute($stalePairIds);
        $pdo->prepare("DELETE FROM bdc_test_scoring_final_results WHERE pair_id IN ($pairPlaceholders)")->execute($stalePairIds);
        $pdo->prepare("DELETE FROM bdc_test_scoring_final_pairs WHERE id IN ($pairPlaceholders)")->execute($stalePairIds);
      }
      $pdo->prepare("UPDATE bdc_test_scoring_entries SET entry_status='withdrawn' WHERE id IN ($stalePlaceholders)")->execute($staleIds);
    }

    $copyEntries=$pdo->prepare("
      INSERT INTO bdc_test_scoring_entries(
        round_id,competitor_id,dance_role,bib_number,display_name,entry_status
      )
      SELECT
        :new_round,se.competitor_id,se.dance_role,se.bib_number,se.display_name,'active'
      FROM bdc_test_scoring_entries se
      JOIN bdc_test_scoring_results sr
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
      FROM bdc_test_scoring_entries
      WHERE round_id=:r AND entry_status='active'
    ");
    $actualStmt->execute(['r'=>$childRoundId]);
    $actual=(int)$actualStmt->fetchColumn();

    if($actual<1){
      throw new RuntimeException('Callbacks could not be transferred to the next round.');
    }

    $judgeCount=$pdo->prepare("SELECT COUNT(*) FROM bdc_test_scoring_judges WHERE round_id=:r");
    $judgeCount->execute(['r'=>$childRoundId]);
    if((int)$judgeCount->fetchColumn()===0){
      $copyJudges=$pdo->prepare("
        INSERT INTO bdc_test_scoring_judges(round_id,judge_name,judge_order,is_chief,scoring_scope)
        SELECT :new_round,judge_name,judge_order,is_chief,scoring_scope
        FROM bdc_test_scoring_judges
        WHERE round_id=:source_round
        ORDER BY judge_order
      ");
      $copyJudges->execute([
        'new_round'=>$childRoundId,
        'source_round'=>$source['id']
      ]);

      $chief=$pdo->prepare("
        SELECT id
        FROM bdc_test_scoring_judges
        WHERE round_id=:r AND is_chief=1
        LIMIT 1
      ");
      $chief->execute(['r'=>$childRoundId]);
      $pdo->prepare("
        UPDATE bdc_test_scoring_rounds
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
       FROM bdc_test_scoring_results sr
       JOIN bdc_test_scoring_entries se ON se.id=sr.entry_id
       WHERE sr.round_id=:r AND sr.result_status='tie_pending'
       GROUP BY se.dance_role,sr.rank_number,sr.total_score
       HAVING COUNT(*)>1
      ) unresolved_ties
    ");
    $pending->execute(['r'=>$source['id']]);
    if((int)$pending->fetchColumn()>0) throw new RuntimeException('Resolve all callback ties before proceeding.');

    $existing=$pdo->prepare("SELECT id FROM bdc_test_scoring_rounds WHERE event_id=:e AND division=:d AND round_type=:t AND (parent_round_id=:parent OR source_round_id=:source) AND status<>'archived' ORDER BY id DESC LIMIT 1");
    $existing->execute(['e'=>$source['event_id'],'d'=>$source['division'],'t'=>$nextType,'parent'=>$source['id'],'source'=>$source['id']]);
    $existingId=(int)$existing->fetchColumn();
    if($existingId>0){
        $pdo->beginTransaction();
        try{
            $pdo->prepare("UPDATE bdc_test_scoring_rounds SET scoring_mode=:mode,scheduled_at=COALESCE(NULLIF(:scheduled,''),scheduled_at) WHERE id=:id")->execute(['mode'=>$source['scoring_mode']??'manual','scheduled'=>$scheduledAt,'id'=>$existingId]);
            syncCallbacksToChildRound($pdo,$source,$existingId,$userId);
            $pdo->prepare("UPDATE bdc_test_scoring_rounds SET status='completed' WHERE id=:id")
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
        $insert=$pdo->prepare("INSERT INTO bdc_test_scoring_rounds(
          event_id,parent_round_id,source_round_id,round_type,scoring_mode,scheduled_at,division,
          yes_count,callback_count,yes_weight,alt1_weight,alt2_weight,alt3_weight,
          status,created_by
        ) VALUES(:e,:p,:s,:t,:mode,NULLIF(:scheduled,''),:d,:yes,:cb,:yw,:a1,:a2,:a3,'draft',:u)");
        $insert->execute([
          'e'=>$source['event_id'],'p'=>$source['id'],'s'=>$source['id'],'t'=>$nextType,'mode'=>$source['scoring_mode']??'manual','scheduled'=>$scheduledAt,
          'd'=>$source['division'],'yes'=>$source['yes_count'],'cb'=>$source['callback_count'],
          'yw'=>$source['yes_weight'],'a1'=>$source['alt1_weight'],'a2'=>$source['alt2_weight'],
          'a3'=>$source['alt3_weight'],'u'=>$userId?:null
        ]);
        $newId=(int)$pdo->lastInsertId();

        syncCallbacksToChildRound($pdo,$source,$newId,$userId);
        $pdo->prepare("UPDATE bdc_test_scoring_rounds SET status='completed' WHERE id=:id")
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
    $judges=$pdo->prepare('SELECT * FROM bdc_test_scoring_judges WHERE round_id=:r ORDER BY judge_order');$judges->execute(['r'=>$rid]);$judges=$judges->fetchAll();
    $q=$pdo->prepare("SELECT se.*,sr.total_score,sr.rank_number,sr.result_status,sr.alternate_rank FROM bdc_test_scoring_entries se LEFT JOIN bdc_test_scoring_results sr ON sr.entry_id=se.id AND sr.round_id=se.round_id WHERE se.round_id=:r AND se.entry_status='active' ORDER BY se.dance_role,sr.rank_number,se.bib_number");$q->execute(['r'=>$rid]);$all=$q->fetchAll();
    $by=['leader'=>[],'follower'=>[]];foreach($all as $x)$by[$x['dance_role']][]=$x;
    $logo=url('public/assets/bdc-logo.png');
    $table=function(string $role,array $rows)use($judges){ob_start();?><table><thead><tr><th><?=strtoupper($role)==='LEADER'?'LEAD #':'FOLLOW #'?></th><th><?=strtoupper($role).'S'?></th><?php foreach($judges as $j):?><th>J<?= (int)$j['judge_order'] ?><?= (int)$j['is_chief']?'*':'' ?></th><?php endforeach;?><th>TOTAL</th><th>CB</th></tr></thead><tbody><?php foreach($rows as $r):?><tr class="<?=e((string)$r['result_status'])?>"><td><?= (int)$r['bib_number'] ?></td><td><?=e($r['display_name'])?></td><?php foreach($judges as $j):?><td></td><?php endforeach;?><td><?=number_format((float)$r['total_score'],1)?></td><td><?=($r['result_status']==='callback')?(int)$r['rank_number']:(($r['result_status']==='alternate')?'A'.(int)$r['alternate_rank']:'')?></td></tr><?php endforeach;?></tbody></table><?php return ob_get_clean();};
    ob_start();?><!doctype html><html><head><meta charset="utf-8"><title>Heats Results</title><style>@page{size:A4 landscape;margin:8mm}body{font-family:Arial,sans-serif;color:#111;margin:0}.head{display:flex;justify-content:space-between;align-items:flex-start}.logo{width:90px}.title{font-weight:700;font-size:18px}.sub{font-weight:700;margin-top:5px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:12mm;margin-top:8mm}table{width:100%;border-collapse:collapse;font-size:10px}th,td{border:1px solid #111;padding:4px;text-align:center}th:nth-child(2),td:nth-child(2){text-align:left}.callback{background:#d1e7dd}.alternate{background:#fff3cd}.tie_pending{background:#f8d7da}.foot{margin-top:8mm;font-size:10px;display:flex;justify-content:space-between}.no-print{margin:10px}@media print{.no-print{display:none}}</style></head><body>
<div style="background:#7f1d1d;color:#fff;padding:10px 16px;text-align:center;font-weight:800;letter-spacing:.06em;position:sticky;top:0;z-index:9999">
 SCORING TESTS DASHBOARD · ISOLATED TEST DATA · NEVER PUBLISHED TO OFFICIAL RESULTS
</div>
<div class="no-print"><button onclick="window.print()">Print / Save as PDF</button></div><div class="head"><div><div class="title"><?=e($round['event_name'])?></div><div class="sub"><?=strtoupper(e($round['division']))?> DIVISION - HEATS</div><div><?=e((string)$round['event_date'])?></div></div><img class="logo" src="<?=e($logo)?>"></div><div class="grid"><section><?=$table('leader',$by['leader'])?></section><section><?=$table('follower',$by['follower'])?></section></div><div class="foot"><div>Witness(es): ______________________________</div><div>Bachata Dance Council · Version <?= (int)$round['generated_version'] ?></div></div><script src="<?=e(url('admin/scoring/heats-live-v230.js?v=230'))?>"></script>
<div id="scoringProgressOverlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.82);z-index:10000;align-items:center;justify-content:center;color:#fff"><div style="background:#111827;padding:28px;border-radius:14px;min-width:320px;text-align:center"><div class="spinner-border mb-3" role="status"></div><h3 class="h5">Processing Scores</h3><div id="scoringProgressText">Saving scores…</div><div class="progress mt-3" style="height:10px"><div id="scoringProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width:20%"></div></div></div></div><script src="../../public/js/final-pairing-sync.js?v=383" defer></script></body></html><?php return ob_get_clean();
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
      ? 'This completed test round is locked. Only a Scorer, Master Scorer or Super Admin can confirm a resubmission override.'
      : ($lockedRound['status']==='pending_approval'
      ? 'This competition is pending Super Admin approval and is temporarily read-only.'
      : 'This competition is archived and read-only. Only Super Admin rollback can reopen it.');
    throw new RuntimeException($message);
   }
  }
  if($action!=='create_round' && !in_array($action,['create_scoring_backup','restore_scoring_backup','delete_scoring_backup','delete_selected_scoring_backups','generate_emcee_link'],true) && !empty($_POST['round_id'])){
   ScoringBackupService::create($pdo,(int)$_POST['round_id'],true,$userId,'automatic',$action,'Before '.str_replace('_',' ',$action));
  }
  if($action==='create_scoring_backup'){
   if(!Auth::canManageScoringBackups())throw new RuntimeException('Only an Admin, Scorer, Master Scorer or Super Admin can create protected test scoring backups.');
   $roundId=(int)($_POST['round_id']??0);if(!loadRound($pdo,$roundId))throw new RuntimeException('Test scoring round not found.');
   $backupId=ScoringBackupService::create($pdo,$roundId,true,$userId,'manual','manual_backup',(string)($_POST['backup_label']??''));
   auditScoring($pdo,$roundId,$userId,'manual_scoring_backup_created',['backup_id'=>$backupId]);$notice='Protected test scoring backup #'.$backupId.' created.';
  }elseif($action==='restore_scoring_backup'){
   if(!Auth::canManageScoringBackups())throw new RuntimeException('Only an Admin, Scorer, Master Scorer or Super Admin can restore test scoring backups.');
   $roundId=(int)($_POST['round_id']??0);$confirmation=strtoupper(trim((string)($_POST['restore_confirmation']??'')));if($confirmation!=='RESTORE SCORES')throw new RuntimeException('Type RESTORE SCORES to confirm recovery.');
   $restored=ScoringBackupService::restore($pdo,(int)($_POST['backup_id']??0),$roundId,true,$userId,(string)($_POST['restore_reason']??''));$notice='Test scoring backup #'.$restored['id'].' restored. A safety copy of the previous state was created first.';
  }elseif($action==='delete_scoring_backup'){
   if(!Auth::canManageScoringBackups())throw new RuntimeException('Only an Admin, Scorer, Master Scorer or Super Admin can delete test scoring backups.');
   $roundId=(int)($_POST['round_id']??0);if(!loadRound($pdo,$roundId))throw new RuntimeException('Test scoring round not found.');
   if(strtoupper(trim((string)($_POST['delete_confirmation']??'')))!=='DELETE BACKUP')throw new RuntimeException('Type DELETE BACKUP to confirm permanent deletion.');
   $deleted=ScoringBackupService::delete($pdo,(int)($_POST['backup_id']??0),$roundId,true,$userId,(string)($_POST['delete_reason']??''));$notice='Test scoring backup #'.$deleted['id'].' permanently deleted. Current test scores were not changed.';
  }elseif($action==='delete_selected_scoring_backups'){
   if(!Auth::canManageScoringBackups())throw new RuntimeException('Only an Admin, Scorer, Master Scorer or Super Admin can delete test scoring backups.');
   $roundId=(int)($_POST['round_id']??0);if(!loadRound($pdo,$roundId))throw new RuntimeException('Test scoring round not found.');
   if(strtoupper(trim((string)($_POST['delete_confirmation']??'')))!=='DELETE SELECTED')throw new RuntimeException('Type DELETE SELECTED to confirm permanent deletion.');
   $ids=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['backup_ids']??[])),static fn(int $id):bool=>$id>0)));if(!$ids)throw new RuntimeException('Select at least one test scoring backup to delete.');
   $deleted=ScoringBackupService::deleteMany($pdo,$ids,$roundId,true,$userId,(string)($_POST['delete_reason']??''));$notice=$deleted['count'].' selected test scoring backup'.($deleted['count']===1?'':'s').' permanently deleted. Current test scores were not changed.';
  }elseif($action==='reopen_completed_round'){
   if(!Auth::canOverrideCompletedScores())throw new RuntimeException('Only a Scorer, Master Scorer or Super Admin can reopen a completed test round.');
   $roundId=(int)($_POST['round_id']??0);
   $confirmation=strtoupper(trim((string)($_POST['resubmit_confirmation']??'')));
   $completed=loadRound($pdo,$roundId);
   if(!$completed||$completed['status']!=='completed')throw new RuntimeException('Completed test round not found.');
   if($confirmation!=='RESUBMIT')throw new RuntimeException('Type RESUBMIT to confirm the scoring override.');
   $pdo->prepare("UPDATE bdc_test_scoring_rounds SET status='draft',locked_at=NULL,locked_by=NULL WHERE id=:id")->execute(['id'=>$roundId]);
   auditScoring($pdo,$roundId,$userId,'completed_round_reopened_for_resubmission',['confirmation'=>'RESUBMIT','child_rounds_preserved'=>true]);
   $notice='Completed test round unlocked for correction and resubmission.';
  }elseif($action==='unlock_all_final_judges'){
   if(!Auth::canOverrideCompletedScores())throw new RuntimeException('Only a Scorer, Master Scorer or Super Admin can use the emergency test unlock.');
   $roundId=(int)($_POST['round_id']??0);$reason=trim((string)($_POST['unlock_all_reason']??''));$confirmation=strtoupper(trim((string)($_POST['unlock_all_confirmation']??'')));
   $finalRound=loadRound($pdo,$roundId);
   if(!$finalRound||$finalRound['round_type']!=='final'||($finalRound['scoring_mode']??'manual')!=='automated')throw new RuntimeException('Automatic test Final round not found.');
   if($confirmation!=='UNLOCK ALL')throw new RuntimeException('Type UNLOCK ALL to confirm the emergency override.');
   $unlocked=TestAutomaticJudgeService::unlockAllSubmitted($pdo,$roundId,$userId,$reason);
   $pdo->prepare("UPDATE bdc_test_scoring_rounds SET status=CASE WHEN status='scores_submitted' THEN 'draft' ELSE status END WHERE id=:id")->execute(['id'=>$roundId]);
   auditScoring($pdo,$roundId,$userId,'all_final_judge_scores_emergency_unlocked',['reason'=>$reason,'affected_count'=>$unlocked['count'],'judge_ids'=>$unlocked['judge_ids']]);
   $notice=$unlocked['count'].' locked test judge score columns reopened. Existing placements were preserved and must be resubmitted.';
  }elseif($action==='generate_test_event'){
   $division=(string)($_POST['division']??'novice');
   $roundType=(string)($_POST['round_type']??'heats');
   $tier=(int)($_POST['competition_tier']??2);
   if(!in_array($division,['novice','intermediate','advanced','all_star'],true)&&!SpecialCategoryService::isSpecial($division))throw new RuntimeException('Invalid division.');
   if(!in_array($roundType,['heats','final'],true))$roundType='heats';
   if(!in_array($tier,[1,2,3],true))$tier=2;

   $eventName='TEST EVENT '.date('Y-m-d H-i-s');
   $slug='test-event-'.date('YmdHis').'-'.random_int(100,999);
   $pdo->prepare("INSERT INTO bdc_test_events(name,normalised_name,slug,event_date,status,points_tier) VALUES(:name,:normalised,:slug,CURDATE(),'draft',:tier)")
       ->execute(['name'=>$eventName,'normalised'=>strtolower($eventName),'slug'=>$slug,'tier'=>(string)$tier]);
   $eventId=(int)$pdo->lastInsertId();

   $yes=[1=>5,2=>10,3=>15][$tier];
   $pdo->prepare("INSERT INTO bdc_test_scoring_rounds(event_id,round_type,scoring_mode,division,yes_count,callback_count,yes_weight,alt1_weight,alt2_weight,alt3_weight,created_by) VALUES(:event,:type,:mode,:division,:yes,:callbacks,10.00,4.50,4.30,4.20,:user)")
       ->execute(['event'=>$eventId,'type'=>$roundType,'mode'=>$testMode,'division'=>$division,'yes'=>$yes,'callbacks'=>$yes,'user'=>$userId]);
   $roundId=(int)$pdo->lastInsertId();
   auditScoring($pdo,$roundId,$userId,'test_event_generated',['tier'=>$tier]);
   $notice='Random test event and scoring round generated.';
  }elseif($action==='generate_test_competitors'){
   $roundId=(int)($_POST['round_id']??0);
   $leaderCount=max(0,min(500,(int)($_POST['leader_count']??10)));
   $followerCount=max(0,min(500,(int)($_POST['follower_count']??10)));
   if($roundId<1)throw new RuntimeException('Open a test round first.');

   $pdo->beginTransaction();
   try{
    $insertEntry=$pdo->prepare("INSERT INTO bdc_test_scoring_entries(round_id,competitor_id,dance_role,bib_number,display_name,entry_status) VALUES(:round,:competitor,:role,:bib,:name,'active') ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),entry_status='active'");
    foreach(['leader'=>$leaderCount,'follower'=>$followerCount] as $role=>$count){
     $existingBib=(int)$pdo->query("SELECT COALESCE(MAX(bib_number),0) FROM bdc_test_scoring_entries WHERE round_id={$roundId} AND dance_role=".$pdo->quote($role))->fetchColumn();
     foreach(testRandomRows($pdo,$role,$count) as $competitor){
      copyOfficialCompetitorToTest($pdo,$competitor);
      $existingBib++;
      $insertEntry->execute([
       'round'=>$roundId,
       'competitor'=>(int)$competitor['id'],
       'role'=>$role,
       'bib'=>$existingBib,
       'name'=>$competitor['exact_name'],
      ]);
     }
    }
    auditScoring($pdo,$roundId,$userId,'random_test_competitors_generated',['leaders'=>$leaderCount,'followers'=>$followerCount]);
    $pdo->commit();
    $tierInfo=applyAutomaticTier($pdo,$roundId,true);$notice='Random test competitors generated. Automatic Tier '.$tierInfo['tier'].' selected from the larger role count of '.$tierInfo['largest'].'.';
   }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
  }elseif($action==='generate_test_judges'){
   $roundId=(int)($_POST['round_id']??0);
   $allCount=max(0,min(101,(int)($_POST['all_judges']??5)));
   $leaderCount=max(0,min(101,(int)($_POST['leader_judges']??0)));
   $followerCount=max(0,min(101,(int)($_POST['follower_judges']??0)));
   $total=$allCount+$leaderCount+$followerCount;
   if($roundId<1)throw new RuntimeException('Open a test round first.');
   if($total<3||$total>101)throw new RuntimeException('Generate between 3 and 101 judges.');
   if($allCount+$leaderCount<3)throw new RuntimeException('Leader panel requires at least 3 judges.');
   if($allCount+$followerCount<3)throw new RuntimeException('Follower panel requires at least 3 judges.');

   $scopes=array_merge(array_fill(0,$allCount,'all'),array_fill(0,$leaderCount,'leader'),array_fill(0,$followerCount,'follower'));
   shuffle($scopes);
   $chiefIndex=random_int(0,$total-1);

   $pdo->beginTransaction();
   try{
    $pdo->prepare("DELETE FROM bdc_test_scoring_judges WHERE round_id=:round")->execute(['round'=>$roundId]);
    $insert=$pdo->prepare("INSERT INTO bdc_test_scoring_judges(round_id,judge_name,judge_order,is_chief,scoring_scope) VALUES(:round,:name,:position,:chief,:scope)");
    $chiefId=0;
    foreach($scopes as $index=>$scope){
     $insert->execute([
      'round'=>$roundId,
      'name'=>'Test Judge '.($index+1),
      'position'=>$index+1,
      'chief'=>$index===$chiefIndex?1:0,
      'scope'=>$scope,
     ]);
     if($index===$chiefIndex)$chiefId=(int)$pdo->lastInsertId();
    }
    $pdo->prepare("UPDATE bdc_test_scoring_rounds SET chief_judge_id=:chief WHERE id=:round")->execute(['chief'=>$chiefId,'round'=>$roundId]);
    auditScoring($pdo,$roundId,$userId,'random_test_judges_generated',['all'=>$allCount,'leaders'=>$leaderCount,'followers'=>$followerCount]);
    $pdo->commit();
    $notice='Random judges generated. Names, assignments and Chief Judge can now be changed manually.';
   }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
  }elseif($action==='generate_test_heats_scores'){
   $roundId=(int)($_POST['round_id']??0);
   $entriesStmt=$pdo->prepare("SELECT * FROM bdc_test_scoring_entries WHERE round_id=:round AND entry_status='active'");
   $entriesStmt->execute(['round'=>$roundId]);$testEntries=$entriesStmt->fetchAll();
   $judgesStmt=$pdo->prepare("SELECT * FROM bdc_test_scoring_judges WHERE round_id=:round ORDER BY judge_order");
   $judgesStmt->execute(['round'=>$roundId]);$testJudges=$judgesStmt->fetchAll();
   if(!$testEntries||!$testJudges)throw new RuntimeException('Generate competitors and judges first.');

   $up=$pdo->prepare("INSERT INTO bdc_test_scoring_marks(round_id,entry_id,judge_id,mark_type,alt_rank,weighted_score,updated_by) VALUES(:round,:entry,:judge,:type,:alt,:weight,:user) ON DUPLICATE KEY UPDATE mark_type=VALUES(mark_type),alt_rank=VALUES(alt_rank),weighted_score=VALUES(weighted_score),updated_by=VALUES(updated_by),updated_at=NOW()");
   foreach($testEntries as $entry){
    foreach($testJudges as $judge){
     $scope=(string)($judge['scoring_scope']??'all');
     if($scope!=='all'&&$scope!==$entry['dance_role'])continue;
     [$type,$alt,$weight]=randomHeatsValue();
     $up->execute(['round'=>$roundId,'entry'=>$entry['id'],'judge'=>$judge['id'],'type'=>$type,'alt'=>$alt,'weight'=>$weight,'user'=>$userId]);
    }
   }
   auditScoring($pdo,$roundId,$userId,'random_test_heats_scores_generated');
   $notice='Random Heats scores generated. Every score remains manually editable.';
  }elseif($action==='generate_test_final_scores'){
   @set_time_limit(180);
   $roundId=(int)($_POST['round_id']??0);
   $pairStmt=$pdo->prepare("SELECT * FROM bdc_test_scoring_final_pairs WHERE round_id=:round AND pairing_status='confirmed' ORDER BY pair_number");
   $pairStmt->execute(['round'=>$roundId]);
   $pairsForTest=$pairStmt->fetchAll();

   $judgeStmt=$pdo->prepare("SELECT * FROM bdc_test_scoring_judges WHERE round_id=:round ORDER BY judge_order");
   $judgeStmt->execute(['round'=>$roundId]);
   $judgesForTest=$judgeStmt->fetchAll();

   if(count($pairsForTest)<2)throw new RuntimeException('Confirm at least two Final pairs first.');
   if(count($judgesForTest)<3)throw new RuntimeException('Save at least three Final judges first.');

   $chiefJudges=array_values(array_filter($judgesForTest,fn($judge)=>(int)$judge['is_chief']===1));
   if(count($chiefJudges)!==1)throw new RuntimeException('Select exactly one Final Chief Judge before generating scores.');

   $started=microtime(true);
   $pdo->beginTransaction();
   try{
    $pdo->prepare("DELETE FROM bdc_test_scoring_final_results WHERE round_id=:round")->execute(['round'=>$roundId]);
    $pdo->prepare("DELETE FROM bdc_test_scoring_final_marks WHERE round_id=:round")->execute(['round'=>$roundId]);

    $insert=$pdo->prepare("INSERT INTO bdc_test_scoring_final_marks(round_id,pair_id,judge_id,rank_value,updated_by)
      VALUES(:round,:pair,:judge,:rank,:user)");

    foreach($judgesForTest as $judge){
     $rankValues=range(1,count($pairsForTest));
     shuffle($rankValues);
     foreach($pairsForTest as $index=>$pair){
      $insert->execute([
       'round'=>$roundId,
       'pair'=>(int)$pair['id'],
       'judge'=>(int)$judge['id'],
       'rank'=>(int)$rankValues[$index],
       'user'=>$userId?:null
      ]);
     }
    }
    $pdo->commit();
   }catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    throw $e;
   }

   // Use the exact production Relative Placement calculator and persistence path.
   calculateRelativePlacement($pdo,$roundId,$userId);
   $elapsed=(int)round((microtime(true)-$started)*1000);
   $pdo->prepare("UPDATE bdc_test_scoring_rounds SET last_calculation_ms=:ms WHERE id=:round")
       ->execute(['ms'=>$elapsed,'round'=>$roundId]);
   auditScoring($pdo,$roundId,$userId,'random_test_final_scores_generated_and_calculated',[
    'pairs'=>count($pairsForTest),
    'judges'=>count($judgesForTest),
    'score_cells'=>count($pairsForTest)*count($judgesForTest),
    'calculation_ms'=>$elapsed
   ]);
   $notice='Random Final rankings generated and Relative Placement calculated in '.$elapsed.' ms. All rankings remain editable.';
  }elseif($action==='reset_test_round_scores'){
   $roundId=(int)($_POST['round_id']??0);
   foreach(['bdc_test_scoring_final_results','bdc_test_scoring_final_marks','bdc_test_scoring_results','bdc_test_scoring_marks'] as $table){
    $pdo->prepare("DELETE FROM {$table} WHERE round_id=:round")->execute(['round'=>$roundId]);
   }
   $pdo->prepare("UPDATE bdc_test_scoring_rounds SET status='draft',generated_version=0 WHERE id=:round")->execute(['round'=>$roundId]);
   auditScoring($pdo,$roundId,$userId,'test_scores_reset');
   $notice='All test scores and calculated results were reset.';
  }elseif($action==='publish_results'){
   throw new RuntimeException('Test competitions cannot be published or added to the official repository.');
  }elseif($action==='create_round'){
   $eventId=(int)($_POST['event_id']??0);
   $newEventName=trim((string)($_POST['new_event_name']??''));
   $newEventDate=trim((string)($_POST['new_event_date']??''));
   $roundSchedule=trim((string)($_POST['scheduled_at']??''));
   $division=(string)($_POST['division']??'novice');
   $roundType=(string)($_POST['round_type']??'heats');
   if(!in_array($division,['novice','intermediate','advanced','all_star'],true))throw new RuntimeException('Invalid division.');
   if(!in_array($roundType,['heats','final'],true))throw new RuntimeException('Invalid round type.');
   if($roundSchedule!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/',$roundSchedule))throw new RuntimeException('Enter a valid round date and time.');
   $roundSchedule=$roundSchedule===''?'':str_replace('T',' ',$roundSchedule).':00';
   if($eventId>0 && $newEventName!=='')throw new RuntimeException('Select an existing event or create a new event, not both.');
   if($eventId<1){
    if($newEventName==='')throw new RuntimeException('Select an existing event or enter a new event name.');
    if($newEventDate!=='' && !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$newEventDate))throw new RuntimeException('Enter the event date as YYYY-MM-DD.');
    $baseSlug=strtolower(trim((string)preg_replace('/[^a-z0-9]+/i','-',$newEventName),'-'));
    if($baseSlug==='')$baseSlug='event';
    $slug=$baseSlug;$n=2;
    $checkSlug=$pdo->prepare('SELECT COUNT(*) FROM bdc_test_events WHERE slug=:slug');
    while(true){$checkSlug->execute(['slug'=>$slug]);if(!(int)$checkSlug->fetchColumn())break;$slug=$baseSlug.'-'.$n++;}
    $eventInsert=$pdo->prepare("INSERT INTO bdc_test_events(name,normalised_name,slug,event_date,status) VALUES(:name,:normalised,:slug,NULLIF(:event_date,''),'draft')");
    $eventInsert->execute(['name'=>$newEventName,'normalised'=>strtolower($newEventName),'slug'=>$slug,'event_date'=>$newEventDate]);
    $eventId=(int)$pdo->lastInsertId();
   }
   $existing=$pdo->prepare("SELECT id FROM bdc_test_scoring_rounds WHERE event_id=:e AND division=:d AND round_type=:rt AND scoring_mode=:mode AND status<>'archived' ORDER BY id DESC LIMIT 1");
   $existing->execute(['e'=>$eventId,'d'=>$division,'rt'=>$roundType,'mode'=>$testMode]);
   $existingId=(int)$existing->fetchColumn();
   if($existingId>0){$roundId=$existingId;$notice=ucfirst($roundType).' round already exists. Existing round opened.';}
   else{
    $s=$pdo->prepare("INSERT INTO bdc_test_scoring_rounds(event_id,round_type,scoring_mode,scheduled_at,division,yes_count,callback_count,yes_weight,alt1_weight,alt2_weight,alt3_weight,created_by) VALUES(:e,:rt,:mode,NULLIF(:scheduled,''),:d,10,10,10.00,4.50,4.30,4.20,:u)");
    $s->execute(['e'=>$eventId,'rt'=>$roundType,'mode'=>$testMode,'scheduled'=>$roundSchedule,'d'=>$division,'u'=>$userId]);
    $roundId=(int)$pdo->lastInsertId();
    auditScoring($pdo,$roundId,$userId,'round_created',['round_type'=>$roundType,'new_event'=>$newEventName!=='']);
    $notice=ucfirst($roundType).' round created.';
   }
  }elseif($action==='delete_scoring_workflow'){
   if(!Auth::isSuperAdmin())throw new RuntimeException('Only the Super Admin can delete a complete scoring workflow.');
   $eventId=(int)($_POST['event_id']??0);
   $division=(string)($_POST['division']??'');
   $confirmation=trim((string)($_POST['delete_confirmation']??''));

   if($eventId<1 || !in_array($division,['novice','intermediate','advanced','all_star'],true)){
    throw new RuntimeException('Invalid scoring workflow.');
   }
   if($confirmation!=='DELETE'){
    throw new RuntimeException('Type DELETE to confirm removal of the complete scoring workflow.');
   }

   $published=$pdo->prepare("
    SELECT COUNT(*)
    FROM bdc_test_scoring_publications p
    JOIN bdc_test_scoring_rounds r ON r.id=p.final_round_id
    WHERE r.event_id=:e AND r.division=:d AND p.status='published'
   ");
   $published->execute(['e'=>$eventId,'d'=>$division]);
   if((int)$published->fetchColumn()>0){
    throw new RuntimeException('This workflow is published. Use Super Admin rollback before deleting it.');
   }

   $roundStmt=$pdo->prepare("SELECT id FROM bdc_test_scoring_rounds WHERE event_id=:e AND division=:d ORDER BY id");
   $roundStmt->execute(['e'=>$eventId,'d'=>$division]);
   $ids=array_map('intval',$roundStmt->fetchAll(PDO::FETCH_COLUMN));
   if(!$ids)throw new RuntimeException('No scoring rounds found.');

   $ph=implode(',',array_fill(0,count($ids),'?'));
   $pdo->beginTransaction();
   try{
    $pairStmt=$pdo->prepare("SELECT id FROM bdc_test_scoring_final_pairs WHERE round_id IN ($ph)");
    $pairStmt->execute($ids);
    $pairIds=array_map('intval',$pairStmt->fetchAll(PDO::FETCH_COLUMN));
    if($pairIds){
     $pph=implode(',',array_fill(0,count($pairIds),'?'));
     $pdo->prepare("DELETE FROM bdc_test_scoring_final_results WHERE pair_id IN ($pph)")->execute($pairIds);
     $pdo->prepare("DELETE FROM bdc_test_scoring_final_marks WHERE pair_id IN ($pph)")->execute($pairIds);
    }

    foreach([
     'bdc_test_scoring_final_results',
     'bdc_test_scoring_final_marks',
     'bdc_test_scoring_final_pairs',
     'bdc_test_scoring_results',
     'bdc_test_scoring_marks',
     'bdc_test_scoring_judges',
     'bdc_test_scoring_entries',
     'bdc_test_scoring_audit'
    ] as $table){
     $pdo->prepare("DELETE FROM {$table} WHERE round_id IN ($ph)")->execute($ids);
    }

    $pubStmt=$pdo->prepare("
      SELECT p.id
      FROM bdc_test_scoring_publications p
      JOIN bdc_test_scoring_rounds r ON r.id=p.final_round_id
      WHERE r.event_id=? AND r.division=? AND p.status='rolled_back'
    ");
    $pubStmt->execute([$eventId,$division]);
    $pubIds=array_map('intval',$pubStmt->fetchAll(PDO::FETCH_COLUMN));
    if($pubIds){
     $pubPh=implode(',',array_fill(0,count($pubIds),'?'));
     $pdo->prepare("DELETE FROM bdc_test_scoring_publication_points WHERE publication_id IN ($pubPh)")->execute($pubIds);
     $pdo->prepare("DELETE FROM bdc_test_scoring_publications WHERE id IN ($pubPh)")->execute($pubIds);
    }

    $pdo->prepare("DELETE FROM bdc_test_scoring_rounds WHERE id IN ($ph)")->execute($ids);
    $pdo->commit();
    $roundId=0;
    $notice='Complete '.ucfirst($division).' test scoring workflow deleted. Event and competitor records were preserved.';
   }catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    throw $e;
   }

  }elseif($action==='settings'){
   $roundId=(int)$_POST['round_id'];
   $tier=(int)($_POST['competition_tier']??2);
   $tierYes=[1=>5,2=>10,3=>15];
   if(!isset($tierYes[$tier]))throw new RuntimeException('Select a valid competition tier.');
   $yes=$tierYes[$tier];
   $pdo->prepare('UPDATE bdc_test_scoring_rounds SET yes_count=:y,callback_count=:c,tier_manual_override=1,yes_weight=10.00,alt1_weight=4.50,alt2_weight=4.30,alt3_weight=4.20 WHERE id=:id')->execute(['y'=>$yes,'c'=>$yes,'id'=>$roundId]);
   auditScoring($pdo,$roundId,$userId,'heats_settings_saved',['tier'=>$tier,'yes_count'=>$yes,'alternate_count'=>3,'weights'=>['yes'=>10.0,'alt1'=>4.5,'alt2'=>4.3,'alt3'=>4.2]]);
   $notice='BDC Tier '.$tier.' settings saved: '.$yes.' YES selections and 3 alternates.';
  }elseif($action==='add_entry'){
   ScoringRosterCheckpointService::assertEditable($pdo,(int)$_POST['round_id'],true);
   $roundId=(int)$_POST['round_id'];$role=(string)$_POST['dance_role'];$bib=(int)$_POST['bib_number'];$term=trim((string)$_POST['competitor_search']);$entryMode=(string)($_POST['entry_mode']??'existing');
   if(!in_array($role,['leader','follower'],true)||$bib<1||$term==='')throw new RuntimeException('Choose role, bib and competitor name.');
   $roundForEntry=loadRound($pdo,$roundId);if(!$roundForEntry)throw new RuntimeException('Round not found.');
   if((string)$roundForEntry['round_type']==='final' && ((int)($roundForEntry['parent_round_id']??0)>0 || (int)($roundForEntry['source_round_id']??0)>0))throw new RuntimeException('BDC callback-derived Finals accept confirmed callbacks only. Direct finalist additions are not permitted.');
   $bibCheck=$pdo->prepare("SELECT se.id,se.display_name FROM bdc_test_scoring_entries se WHERE se.round_id=:r AND se.dance_role=:role AND se.bib_number=:bib AND se.entry_status='active' LIMIT 1");
   $bibCheck->execute(['r'=>$roundId,'role'=>$role,'bib'=>$bib]);$bibTaken=$bibCheck->fetch();
   if($bibTaken)throw new RuntimeException('Bib '.$bib.' is already assigned to '.$bibTaken['display_name'].' on the '.ucfirst($role).' side.');
   $selectedBdc='';
   if(preg_match('/^(BDC-\d+)/i',$term,$m))$selectedBdc=strtoupper($m[1]);
   $comp=null;
   if($entryMode!=='create'){
    $c=$pdo->prepare("SELECT id,bdc_id,exact_name FROM bdc_test_competitors WHERE bdc_id=:bdc OR id=:num OR LOWER(exact_name)=LOWER(:exact) ORDER BY exact_name LIMIT 1");
    $c->execute(['bdc'=>$selectedBdc!==''?$selectedBdc:$term,'num'=>ctype_digit($term)?(int)$term:0,'exact'=>$term]);$comp=$c->fetch()?:null;
    if(!$comp){
     $c=$pdo->prepare("SELECT id,bdc_id,exact_name FROM bdc_test_competitors WHERE exact_name LIKE :like ORDER BY exact_name LIMIT 2");
     $c->execute(['like'=>'%'.$term.'%']);$matches=$c->fetchAll();
     if(count($matches)===1)$comp=$matches[0];
     elseif(count($matches)>1)throw new RuntimeException('Several competitors match this name. Select the correct BDC ID from the suggestions.');
    }
   }
   if(!$comp){
    if($entryMode!=='create')throw new RuntimeException('Competitor not found. Select a suggestion or click Create Name & Add.');
    $normalised=strtolower(trim((string)preg_replace('/\s+/',' ',$term)));
    $existingName=$pdo->prepare("SELECT id,bdc_id,exact_name FROM bdc_test_competitors WHERE normalised_name=:n ORDER BY id LIMIT 1");
    $existingName->execute(['n'=>$normalised]);$same=$existingName->fetch();
    if($same)throw new RuntimeException('A competitor with this name already exists: '.$same['exact_name'].' ('.$same['bdc_id'].'). Select the existing record.');
    $pdo->beginTransaction();
    try{
     $next=(int)$pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(bdc_id,5) AS UNSIGNED)),0)+1 FROM bdc_test_competitors WHERE bdc_id LIKE 'BDC-%'")->fetchColumn();
     $bdcId='BDC-'.str_pad((string)$next,6,'0',STR_PAD_LEFT);
     $ins=$pdo->prepare("INSERT INTO bdc_test_competitors(bdc_id,exact_name,normalised_name,dance_role,current_division,status,is_historical) VALUES(:bdc,:name,:normalised,:role,:division,'pending',0)");
     $ins->execute(['bdc'=>$bdcId,'name'=>$term,'normalised'=>$normalised,'role'=>$role,'division'=>$roundForEntry['division']]);
     $comp=['id'=>(int)$pdo->lastInsertId(),'bdc_id'=>$bdcId,'exact_name'=>$term];
     $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
   }
   $existingEntry=$pdo->prepare("SELECT dance_role,bib_number,display_name FROM bdc_test_scoring_entries WHERE round_id=:round AND competitor_id=:competitor AND entry_status='active' LIMIT 1");$existingEntry->execute(['round'=>$roundId,'competitor'=>$comp['id']]);if($already=$existingEntry->fetch())throw new RuntimeException($already['display_name'].' is already entered as '.ucfirst((string)$already['dance_role']).' with bib #'.(int)$already['bib_number'].'. Change the existing bib instead of adding the competitor again.');
   $pdo->prepare("INSERT INTO bdc_test_scoring_entries(round_id,competitor_id,dance_role,bib_number,display_name) VALUES(:r,:c,:role,:bib,:n)")->execute(['r'=>$roundId,'c'=>$comp['id'],'role'=>$role,'bib'=>$bib,'n'=>$comp['exact_name']]);
   auditScoring($pdo,$roundId,$userId,'entry_added',['competitor_id'=>$comp['id'],'bdc_id'=>$comp['bdc_id'],'role'=>$role,'bib'=>$bib,'provisional'=>$entryMode==='create']);
   $notice=ucfirst($role).' added: '.$comp['exact_name'].' ('.$comp['bdc_id'].').';
  }elseif($action==='update_bib'){
   ScoringRosterCheckpointService::assertEditable($pdo,(int)($_POST['round_id']??0),true);
   $roundId=(int)($_POST['round_id']??0);$entryId=(int)($_POST['entry_id']??0);$newBib=(int)($_POST['bib_number']??0);
   if($roundId<1||$entryId<1||$newBib<1)throw new RuntimeException('Enter a valid bib number.');
   $entryStmt=$pdo->prepare("SELECT id,dance_role,bib_number,display_name FROM bdc_test_scoring_entries WHERE id=:id AND round_id=:r AND entry_status='active'");
   $entryStmt->execute(['id'=>$entryId,'r'=>$roundId]);$entry=$entryStmt->fetch();
   if(!$entry)throw new RuntimeException('Scoring entry not found.');
   $duplicate=$pdo->prepare("SELECT display_name FROM bdc_test_scoring_entries WHERE round_id=:r AND dance_role=:role AND bib_number=:bib AND entry_status='active' AND id<>:id LIMIT 1");
   $duplicate->execute(['r'=>$roundId,'role'=>$entry['dance_role'],'bib'=>$newBib,'id'=>$entryId]);$takenBy=$duplicate->fetchColumn();
   if($takenBy)throw new RuntimeException('Bib '.$newBib.' is already assigned to '.$takenBy.' on the '.ucfirst($entry['dance_role']).' side.');
   $pdo->prepare("UPDATE bdc_test_scoring_entries SET bib_number=:bib WHERE id=:id AND round_id=:r")->execute(['bib'=>$newBib,'id'=>$entryId,'r'=>$roundId]);
   auditScoring($pdo,$roundId,$userId,'bib_updated',['entry_id'=>$entryId,'role'=>$entry['dance_role'],'old_bib'=>(int)$entry['bib_number'],'new_bib'=>$newBib]);
   $notice=$entry['display_name'].' bib updated to '.$newBib.'.';
  }elseif($action==='remove_entry'){
   $roundId=(int)$_POST['round_id'];ScoringRosterCheckpointService::assertEditable($pdo,$roundId,true);$id=(int)$_POST['entry_id'];$pdo->prepare("UPDATE bdc_test_scoring_entries SET entry_status='withdrawn' WHERE id=:id AND round_id=:r")->execute(['id'=>$id,'r'=>$roundId]);auditScoring($pdo,$roundId,$userId,'entry_removed',['entry_id'=>$id]);$notice='Entry removed.';
  }elseif($action==='save_competitors'){
   $roundId=(int)$_POST['round_id'];ScoringRosterCheckpointService::checkpoint($pdo,$roundId,$userId,'save',true);$notice='Test competitor roster checkpoint saved as draft.';
  }elseif($action==='submit_competitors'){
   $roundId=(int)$_POST['round_id'];ScoringRosterCheckpointService:…16441 tokens truncated…This increases the role count from <?=$promotionRoleState['current']?> to <?=$promotionRoleState['current']+1?>. Any unscored Final pairing will be reset.');"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="add_next_finalist"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="dance_role" value="<?=$promotionRole?>"><button class="btn btn-<?=e($promotionColour)?> btn-sm">Promote Next Ranked <?=e(ucfirst($promotionRole))?></button></form><?php endif;?>
  </div></div>
 <?php endforeach;?>
 </div>
 <?php if($nextRankedState['scoring_started']):?><div class="alert alert-warning mt-3 mb-0">Final scoring has started, so finalist promotion is locked.</div><?php endif;?>
</div></div>
<?php endif;?>

<div class="row g-3 mb-4">
 <div class="col-lg-6"><div class="card shadow-sm h-100">
  <div class="card-header fw-semibold bg-primary-subtle d-flex justify-content-between align-items-center">
   <span>Finalist Leaders</span>

  </div>
  <div class="card-body">
  <?php if(!$entries['leader']):?><div class="text-muted">No finalist Leaders yet.</div><?php endif;?>
  <?php foreach($entries['leader'] as $x):?>
   <div class="border rounded p-2 mb-2 d-flex justify-content-between align-items-center gap-2">
    <span><strong>Bib <?=$x['bib_number']?></strong> · <?=e($x['display_name'])?></span>
    <form method="post" onsubmit="return confirm('Remove this Leader from the Final only?');">
     <input type="hidden" name="_csrf" value="<?=e($csrf)?>">
     <input type="hidden" name="action" value="remove_finalist">
     <input type="hidden" name="round_id" value="<?=$roundId?>">
     <input type="hidden" name="entry_id" value="<?=$x['id']?>">
     <button class="btn btn-sm btn-outline-danger">Remove</button>
    </form>
   </div>
  <?php endforeach;?>
  </div>
 </div></div>

 <div class="col-lg-6"><div class="card shadow-sm h-100">
  <div class="card-header fw-semibold bg-danger-subtle d-flex justify-content-between align-items-center">
   <span>Finalist Followers</span>

  </div>
  <div class="card-body">
  <?php if(!$entries['follower']):?><div class="text-muted">No finalist Followers yet.</div><?php endif;?>
  <?php foreach($entries['follower'] as $x):?>
   <div class="border rounded p-2 mb-2 d-flex justify-content-between align-items-center gap-2">
    <span><strong>Bib <?=$x['bib_number']?></strong> · <?=e($x['display_name'])?></span>
    <form method="post" onsubmit="return confirm('Remove this Follower from the Final only?');">
     <input type="hidden" name="_csrf" value="<?=e($csrf)?>">
     <input type="hidden" name="action" value="remove_finalist">
     <input type="hidden" name="round_id" value="<?=$roundId?>">
     <input type="hidden" name="entry_id" value="<?=$x['id']?>">
     <button class="btn btn-sm btn-outline-danger">Remove</button>
    </form>
   </div>
  <?php endforeach;?>
  </div>
 </div></div>
</div>

<?php if(in_array(($round['scoring_mode']??'manual'),['manual','automated'],true)):?>
<div class="card shadow-sm mb-4 border-dark"><div class="card-body">
 <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-2"><div><h2 class="h5 mb-1">Test Final Judge Selection</h2><div class="text-muted small">Set the isolated Relative Placement judging panel before matching couples, then select exactly one Chief Judge.</div></div><span class="badge text-bg-danger"><?=($round['scoring_mode']??'manual')==='automated'?'TEST · AUTOMATIC FINAL':'TEST · MANUAL FINAL'?></span></div>
 <form method="post" id="finalJudgesForm">
  <input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="save_final_judges"><input type="hidden" name="round_id" value="<?=$roundId?>">
  <div id="finalJudgesWrap">
  <?php $finalJudgeDisplay=$judges?:[['id'=>0,'judge_name'=>'','is_chief'=>1],['id'=>0,'judge_name'=>'','is_chief'=>0],['id'=>0,'judge_name'=>'','is_chief'=>0]];foreach($finalJudgeDisplay as $i=>$judge):$judgeKey='judge_'.$i;?>
   <div class="input-group mb-2 judge-row" data-judge-row><span class="input-group-text final-judge-number">Judge <?=$i+1?></span><input type="hidden" name="final_judges[<?=$judgeKey?>][id]" value="<?=e((string)($judge['id']??0))?>"><input class="form-control" name="final_judges[<?=$judgeKey?>][name]" value="<?=e($judge['judge_name'])?>" placeholder="Final judge name" required><span class="input-group-text"><input type="radio" name="final_chief_key" value="<?=$judgeKey?>" <?=(int)$judge['is_chief']?'checked':''?>> Chief</span><button type="button" class="btn btn-outline-danger" onclick="removeFinalJudge(this)">Remove</button></div>
  <?php endforeach;?>
  </div>
  <div class="d-flex gap-2 flex-wrap"><button type="button" class="btn btn-outline-secondary btn-sm" onclick="addFinalJudge()">+ Add Final Judge</button><button class="btn btn-dark btn-sm">Save Test Final Judges</button></div><div data-final-judge-status hidden></div><?php if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='save_final_judges'&&$error!==''):?><div class="alert alert-danger py-2 mt-2 mb-0"><?=e($error)?></div><?php elseif($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='save_final_judges'&&$notice!==''):?><div class="alert alert-success py-2 mt-2 mb-0"><?=e($notice)?></div><?php endif;?>
 </form>
</div></div>
<?php endif;?>

<div class="card shadow-sm mb-4"><div class="card-body">
 <?php $randomMatchLocked=App\Services\RandomPairingService::scoringStarted($pdo,$roundId,true);$emceeLink=App\Services\RandomPairingService::activeLink($pdo,$roundId,true);?>
 <div class="border border-danger-subtle rounded p-3 mb-3 bg-danger-subtle bg-opacity-10">
  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
   <div><h2 class="h5 mb-1">Test Emcee Matching Link</h2><div class="text-muted small">Restricted link for the Emcee to randomize and reveal Final couples on this event's existing Test projector.</div></div>
   <a class="btn btn-outline-secondary" target="_blank" rel="noopener" href="../live-screen/control.php?round_id=<?=$roundId?>&amp;data_mode=test#emcee-match">Manage Test Event Projection</a>
  </div>
  <?php if($emceeLink):?>
   <div class="input-group mt-3"><input id="testEmceeMatchingUrl" class="form-control" readonly value="<?=e((string)$emceeLink['url'])?>"><button type="button" class="btn btn-outline-primary" onclick="navigator.clipboard.writeText(document.getElementById('testEmceeMatchingUrl').value)">Copy Link</button><a class="btn btn-danger" target="_blank" rel="noopener" href="<?=e((string)$emceeLink['url'])?>">Open Emcee Matching</a></div>
   <div class="small text-muted mt-1">Secure access expires <?=e((string)$emceeLink['expires_at'])?>.</div>
  <?php else:?><p class="text-muted small mt-3 mb-0">Generate the restricted link when the Emcee is ready. Final scoring must not have started.</p><?php endif;?>
  <form method="post" class="mt-2"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="generate_emcee_link"><input type="hidden" name="round_id" value="<?=$roundId?>"><button class="btn btn-outline-danger" <?=$randomMatchLocked?'disabled':''?>><?=$emceeLink?'Regenerate':'Generate'?> Test Emcee Matching Link</button></form>
 </div>
 <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div><h2 class="h5 mb-1">Match Competitors</h2><div class="text-muted small">Choose one Follower beside each Leader, or generate a random match.</div></div>
  <?php if($emceeLink):?><form method="post" target="_blank" action="<?=e((string)$emceeLink['url'])?>" onsubmit="return confirm('Start the secure Emcee Random Match and projector countdown now?');"><input type="hidden" name="action" value="randomize"><button class="btn btn-warning">Start Test Emcee Random Match</button></form><?php else:?><form method="post">
   <input type="hidden" name="_csrf" value="<?=e($csrf)?>">
   <input type="hidden" name="action" value="random_final_pairing">
   <input type="hidden" name="round_id" value="<?=$roundId?>">
   <button class="btn btn-warning" <?=$randomMatchLocked?'disabled':''?>><?=$randomMatchLocked?'Random Match Locked':'Random Match'?></button>
  </form><?php endif;?>
 </div>
 <?php if($randomMatchLocked):?><div class="alert alert-warning"><strong>Random Match locked:</strong> Test Final scoring has started, so the current couples are protected.</div><?php if(Auth::canOverrideCompletedScores()):?><details class="border border-danger-subtle rounded p-3 mb-3"><summary class="fw-bold text-danger">Emergency REMATCH override</summary><p class="small text-muted mt-2">This clears all existing Test Final placements and results, reopens every judge session, and revokes the Test Emcee match link.</p><form method="post" class="row g-2" onsubmit="return confirm('Emergency REMATCH will clear every existing Test Final placement and result. Continue?');"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="unlock_random_pairing"><input type="hidden" name="round_id" value="<?=$roundId?>"><div class="col-md-7"><input class="form-control" name="rematch_reason" minlength="8" maxlength="500" required placeholder="Reason for emergency rematch"></div><div class="col-md-3"><input class="form-control" name="rematch_confirmation" required autocomplete="off" placeholder="Type REMATCH"></div><div class="col-md-2"><button class="btn btn-outline-danger w-100">Unlock</button></div></form></details><?php endif;?><?php endif;?>

 <form method="post" id="finalPairingForm" data-pairing-state-url="../scoring/final-pairing-state.php?round_id=<?=$roundId?>&amp;data_mode=test">
  <input type="hidden" name="_csrf" value="<?=e($csrf)?>">
  <input type="hidden" name="round_id" value="<?=$roundId?>">
  <div class="table-responsive"><table class="table align-middle">
   <thead><tr><th>Couple</th><th>Leader</th><th>Follower</th><th>Status</th></tr></thead>
   <tbody>
   <?php
   $pairMap=[];foreach($finalPairs as $pair)$pairMap[(int)$pair['leader_entry_id']]=$pair;
   foreach($entries['leader'] as $i=>$leader):
    $current=$pairMap[(int)$leader['id']]??null;
   ?>
    <tr>
     <td>#<?=$i+1?></td>
     <td><strong>Bib <?=$leader['bib_number']?></strong><br><?=e($leader['display_name'])?></td>
     <td>
      <select class="form-select" data-final-leader-id="<?=$leader['id']?>" name="pair[<?=$leader['id']?>]">
       <option value="0">Select Follower</option>
       <?php foreach($entries['follower'] as $follower):?>
        <option value="<?=$follower['id']?>" <?=$current && (int)$current['follower_entry_id']===(int)$follower['id']?'selected':''?>>
         Bib <?=$follower['bib_number']?> · <?=e($follower['display_name'])?>
        </option>
       <?php endforeach;?>
      </select>
     </td>
     <td data-final-pair-status="<?=$leader['id']?>"><?=e($current['pairing_status']??'draft')?></td>
    </tr>
   <?php endforeach;?>
   </tbody>
  </table></div>
  <div class="small text-muted mb-2" data-pairing-sync-status>Waiting for Emcee Random Match…</div>
  <div class="d-flex gap-2 flex-wrap">
   <button class="btn btn-outline-primary" name="action" value="save_final_pairing">Save Pairing Draft</button>
   <button class="btn btn-success" name="action" value="confirm_final_pairing" onclick="return confirm('Confirm these fixed Final couples?')">Confirm Final Pairing</button>
  </div>
 </form>
</div></div>

<?php
$pairingConfirmed=$finalPairs && count(array_filter($finalPairs,fn($pair)=>$pair['pairing_status']==='confirmed'))===count($finalPairs);
?>
<?php if($pairingConfirmed):?>
<?php $finalRankMaximum=min(20,count($finalPairs));$finalRankMinimum=min(3,$finalRankMaximum);$finalRankCount=min($finalRankMaximum,max($finalRankMinimum,(int)$round['callback_count']));$finalRankSettingLocked=!empty($finalMarks);?>
<div class="card shadow-sm mb-4"><div class="card-body">
 <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
  <div>
   <h2 class="h5 mb-1">Final Relative Placement Scoring</h2>
   <p class="text-muted mb-0">Each judge ranks the selected Top <?=$finalRankCount?> couples. Every rank from 1 to <?=$finalRankCount?> must be used once; all other couples remain unranked.</p>
  </div>
  <form method="post" class="d-flex align-items-end gap-2 flex-wrap mb-3"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="save_final_rank_count"><input type="hidden" name="round_id" value="<?=$roundId?>"><div><label class="form-label fw-semibold">Placements each judge must rank</label><select class="form-select" name="final_rank_count" <?=$finalRankSettingLocked?'disabled':''?>><?php for($rankOption=$finalRankMinimum;$rankOption<=$finalRankMaximum;$rankOption++):?><option value="<?=$rankOption?>" <?=$rankOption===$finalRankCount?'selected':''?>>Top <?=$rankOption?></option><?php endfor;?></select></div><?php if($finalRankSettingLocked):?><span class="badge text-bg-secondary mb-2">Locked because judging has started</span><?php else:?><button class="btn btn-dark mb-0">Save Final Ranking Depth</button><?php endif;?></form>
  <a class="btn btn-outline-primary" href="print.php?round_id=<?=$roundId?>" target="_blank">Print Final Judge Sheets</a>
 </div>

 <form method="post" id="finalScoreForm" <?php if(($round['scoring_mode']??'manual')==='automated'):?>data-final-score-state-url="../scoring/final-score-state.php?round_id=<?=$roundId?>&amp;data_mode=test"<?php endif;?>>
  <input type="hidden" name="_csrf" value="<?=e($csrf)?>">
  <input type="hidden" name="round_id" value="<?=$roundId?>">
  <input type="hidden" name="final_rank_payload" id="finalRankPayload" value="">
  <?php if(($round['scoring_mode']??'manual')==='automated'):?><div class="alert alert-info py-2 mb-3" data-final-score-sync-status>Live judge-score updates are connecting…</div><?php endif;?>
  <?php $finalJudgePageSize=10;$finalJudgePageCount=max(1,(int)ceil(count($judges)/$finalJudgePageSize));?>
  <?php if(count($judges)>10):?><div class="d-flex flex-wrap align-items-center gap-2 mb-3">
   <strong>Judge columns:</strong>
   <?php for($judgePage=0;$judgePage<$finalJudgePageCount;$judgePage++):$start=$judgePage*$finalJudgePageSize+1;$end=min(count($judges),($judgePage+1)*$finalJudgePageSize);?>
    <button type="button" class="btn btn-sm <?=$judgePage===0?'btn-dark':'btn-outline-dark'?> final-judge-page-button" data-page="<?=$judgePage?>">J<?=$start?>–J<?=$end?></button>
   <?php endfor;?>
   <span class="text-muted small">Only one judge group is shown at a time. All scores remain saved.</span>
  </div><?php endif;?>
  <div class="table-responsive"><table class="table table-bordered align-middle final-scoring-table">
   <thead><tr>
    <th>Final Rank</th><th>Couple</th><th>Leader</th><th>Follower</th>
    <?php foreach($judges as $judgeIndex=>$judge):$judgeLocked=($round['scoring_mode']??'manual')==='automated'&&($judgeSessionStatus[(int)$judge['id']]??'')==='submitted';?><th class="final-judge-column" data-final-judge-header="<?=$judge['id']?>" data-judge-page="<?=intdiv($judgeIndex,$finalJudgePageSize)?>" <?=$judgeIndex>=$finalJudgePageSize?'style="display:none"':''?>>J<?=$judgeIndex+1?><?=(int)$judge['is_chief']?' ★':''?><?=$judgeLocked?' 🔒':''?></th><?php endforeach;?>
    <th>Relative Placement</th>
   </tr></thead>
   <tbody>
   <?php foreach($finalPairs as $pair):$finalResult=$finalResults[(int)$pair['id']]??null;?>
    <tr>
     <td class="fw-bold" data-final-rank="<?=$pair['id']?>"><?= $finalResult ? (int)$finalResult['final_rank'] : '—' ?></td>
     <td>Couple <?=$pair['pair_number']?></td>
     <td><strong>Bib <?=$pair['leader_bib']?></strong><br><?=e($pair['leader_name'])?></td>
     <td><strong>Bib <?=$pair['follower_bib']?></strong><br><?=e($pair['follower_name'])?></td>
     <?php foreach($judges as $judgeIndex=>$judge):$judgeLocked=($round['scoring_mode']??'manual')==='automated'&&($judgeSessionStatus[(int)$judge['id']]??'')==='submitted';?>
      <td class="final-judge-column" data-judge-page="<?=intdiv($judgeIndex,$finalJudgePageSize)?>" <?=$judgeIndex>=$finalJudgePageSize?'style="display:none"':''?>><input class="form-control form-control-sm text-center final-rank-input <?=$judgeLocked?'bg-light':''?>" type="number" min="1" max="<?=$finalRankCount?>" data-pair-id="<?=$pair['id']?>" data-judge-id="<?=$judge['id']?>" data-server-value="<?=e((string)($finalMarks[(int)$pair['id']][(int)$judge['id']]??''))?>" name="final_rank[<?=$pair['id']?>][<?=$judge['id']?>]" value="<?=e((string)($finalMarks[(int)$pair['id']][(int)$judge['id']]??''))?>" <?=$judgeLocked?'readonly aria-label="Submitted judge placement locked" title="Submitted placement locked. Use the audited RESUBMIT control to reopen this judge."':''?>></td>
     <?php endforeach;?>
     <td data-final-result="<?=$pair['id']?>">
      <?php if($finalResult):?>
       <div class="fw-semibold">Relative Placement Summary</div>
       <div>✓ Majority achieved in Top <?=$finalResult['majority_level']?></div>
       <div>✓ <?=$finalResult['majority_count']?> of <?=count($judges)?> judges ranked this couple in the Top <?=$finalResult['majority_level']?></div>
       <div>✓ Best placement score among qualifying couples</div>
      <?php else:?>Not calculated<?php endif;?>
     </td>
    </tr>
   <?php endforeach;?>
   </tbody>
  </table></div>
  <div class="border rounded bg-light p-3 mb-3">
   <div class="fw-semibold mb-2">Final Judge Key</div>
   <div class="d-flex flex-wrap gap-3 small">
    <?php foreach($judges as $judgeIndex=>$judge):?>
     <span><strong>J<?=$judgeIndex+1?></strong> · <?=e($judge['judge_name'])?><?=(int)$judge['is_chief']?' ★ Chief Judge':''?></span>
    <?php endforeach;?>
   </div>
  </div>
  <div class="card border-0 bg-light mb-3"><div class="card-body">
   <h3 class="h6 mb-3">Final Scoring Witnesses</h3>
   <div class="row g-2">
    <div class="col-md-3"><label class="form-label">Witness 1</label><input class="form-control" name="witness_1" maxlength="190" value="<?=e((string)($round['witness_1']??''))?>" placeholder="Witness name"></div>
    <div class="col-md-3"><label class="form-label">Witness 2</label><input class="form-control" name="witness_2" maxlength="190" value="<?=e((string)($round['witness_2']??''))?>" placeholder="Witness name"></div>
    <div class="col-md-3"><label class="form-label">Witness 3</label><input class="form-control" name="witness_3" maxlength="190" value="<?=e((string)($round['witness_3']??''))?>" placeholder="Witness name"></div>
    <div class="col-md-3"><label class="form-label">Scoring Administrator</label><input class="form-control" name="scoring_administrator" maxlength="190" value="<?=e((string)($round['scoring_administrator']??''))?>" placeholder="Administrator name"></div>
   </div>
  </div></div>
  <div class="d-flex flex-wrap gap-2 align-items-center">
   <button class="btn btn-outline-dark" name="action" value="save_final_scores">Save Final Draft</button>
   <button class="btn btn-outline-danger" name="action" value="generate_test_final_scores" formnovalidate>Generate Random Final Rankings + Calculate Relative Placement</button>
   <button class="btn btn-success" name="action" value="calculate_final_ranking">Calculate &amp; Sort Final Ranking</button>
   <button class="btn btn-primary" name="action" value="submit_final_scores">Submit Final Scores</button>
   <?php if($finalResults):?>
    <a class="btn btn-outline-primary" target="_blank" href="final-result.php?round_id=<?=$roundId?>">Print Final Scoring Sheet</a>
    <a class="btn btn-danger" href="publish.php?round_id=<?=$roundId?>">Review &amp; Publish Competition</a>
   <?php endif;?>
  </div>
 </form>
 <?php $lockedFinalJudgeCount=count(array_filter($judges,fn(array $judge):bool=>($judgeSessionStatus[(int)$judge['id']]??'')==='submitted'));?>
 <?php if(($round['scoring_mode']??'manual')==='automated'&&$lockedFinalJudgeCount>0&&Auth::canOverrideCompletedScores()):?>
 <section class="border border-danger rounded p-3 mt-3"><div class="fw-bold text-danger">Emergency Scoring Control</div><div class="small text-muted mb-2">Reopens all <?=$lockedFinalJudgeCount?> submitted test judge columns together. Existing placements are preserved and every affected judge must resubmit.</div><form method="post" class="row g-2 align-items-end" onsubmit="return confirm('Emergency unlock all <?=$lockedFinalJudgeCount?> submitted test judge score columns? Existing placements stay saved, but every affected judge must resubmit.');"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="unlock_all_final_judges"><input type="hidden" name="round_id" value="<?=$roundId?>"><div class="col-lg-6"><label class="form-label small fw-semibold">Required emergency reason</label><input class="form-control" name="unlock_all_reason" maxlength="500" required></div><div class="col-lg-3"><label class="form-label small fw-semibold">Type UNLOCK ALL</label><input class="form-control" name="unlock_all_confirmation" autocomplete="off" required></div><div class="col-lg-3"><button class="btn btn-danger w-100">Unlock All Locked Scores (<?=$lockedFinalJudgeCount?>)</button></div></form></section>
 <?php endif;?>
</div></div>
<?php else:?>
<div class="alert alert-secondary">
 <strong>Next step:</strong> confirm fixed couples to open <?=($round['scoring_mode']??'manual')==='automated'?'Automatic Final Judge Scoring':'manual Relative Placement scoring'?>.
</div>
<?php endif;?>
<?php else:?>
<?php
$currentTier=(int)$round['yes_count']===5?1:((int)$round['yes_count']===15?3:2);
?>
<div class="row g-3 mb-4"><div class="col-lg-4"><div class="card shadow-sm h-100"><div class="card-body">
<h2 class="h5"><?=e(ucfirst($round['round_type']))?> Settings</h2>
<form method="post" class="row g-3">
<input type="hidden" name="_csrf" value="<?=e($csrf)?>">
<input type="hidden" name="action" value="settings">
<input type="hidden" name="round_id" value="<?=$roundId?>">
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
</div></div></div>
<div class="col-lg-8"><div class="card shadow-sm h-100" id="judge-setup"><div class="card-body"><h2 class="h5">Judge Setup</h2><div class="small text-muted mb-3">Default is All. Each role panel must contain at least 3 judges.</div><form method="post" id="judgesForm"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="save_judges"><input type="hidden" name="round_id" value="<?=$roundId?>"><div id="judgesWrap"><?php $display=$judges?:[['judge_name'=>'','is_chief'=>1,'scoring_scope'=>'all'],['judge_name'=>'','is_chief'=>0,'scoring_scope'=>'all'],['judge_name'=>'','is_chief'=>0,'scoring_scope'=>'all']];foreach($display as $i=>$j):?><div class="row g-2 mb-2 judge-row align-items-center"><div class="col-md-2"><strong>Judge <?=$i+1?></strong></div><div class="col-md-5"><input class="form-control" name="judge_name[]" value="<?=e($j['judge_name'])?>" placeholder="Judge name" required></div><div class="col-md-3"><select class="form-select" name="judge_scope[]"><?php foreach(['all'=>'All','leader'=>'Leaders','follower'=>'Followers'] as $scopeValue=>$scopeLabel):?><option value="<?=$scopeValue?>" <?=($j['scoring_scope']??'all')===$scopeValue?'selected':''?>><?=$scopeLabel?></option><?php endforeach;?></select></div><div class="col-md-2"><label><input type="radio" name="chief_index" value="<?=$i?>" <?=(int)$j['is_chief']?'checked':''?>> Chief</label></div></div><?php endforeach;?></div><div class="d-flex gap-2 flex-wrap"><button type="button" class="btn btn-outline-secondary btn-sm" onclick="addJudge()">+ Judge</button><button class="btn btn-dark btn-sm">Submit Judges</button><a class="btn btn-outline-primary btn-sm" href="print.php?round_id=<?=$roundId?>" target="_blank">Generate Judge Sheets</a></div></form></div></div></div></div>
<datalist id="competitorSuggestions"><?php foreach($competitorSuggestions as $suggestion):if(isset($activeCompetitorIds[(int)($suggestion['id']??0)]))continue;?><option value="<?=e($suggestion['bdc_id'])?>"><?=e($suggestion['exact_name'].' · '.ucfirst($suggestion['dance_role']).($suggestion['status']==='pending'?' · Details pending':''))?></option><?php endforeach;?></datalist>
<fieldset <?=$rosterSubmitted?'disabled':''?>><div class="row g-3 mb-4">
<div class="col-lg-6"><div class="card shadow-sm role-card"><div class="card-header bg-primary-subtle fw-semibold">Leaders</div><div class="card-body">
<form method="post" class="row g-2 mb-3"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="add_entry"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="dance_role" value="leader">
<div class="col-3"><input class="form-control" type="number" min="1" name="bib_number" value="<?=$nextBib['leader']?>" aria-label="Leader bib number" required><div class="form-text">Next suggested bib. You can overwrite it.</div></div>
<div class="col-9"><input class="form-control" name="competitor_search" list="competitorSuggestions" placeholder="Type competitor name or BDC ID" required><div class="form-text">Select an existing BDC ID, or type a new name and use Create Name &amp; Add.</div></div>
<div class="col-6"><button class="btn btn-primary w-100" name="entry_mode" value="existing">Add Existing</button></div>
<div class="col-6"><button class="btn btn-outline-primary w-100" name="entry_mode" value="create" onclick="return confirm('Create a provisional BDC competitor using only this name? The competitor can complete details later.')">Create Name &amp; Add</button></div>
</form>
<table class="table table-sm align-middle"><thead><tr><th style="width:150px">Bib</th><th>Competitor</th><th>BDC ID</th><th style="width:100px"></th></tr></thead><tbody><?php foreach($entries['leader'] as $x):?><tr><td><form method="post" class="d-flex gap-1"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="update_bib"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="entry_id" value="<?=$x['id']?>"><input class="form-control form-control-sm" style="width:76px" type="number" min="1" name="bib_number" value="<?=$x['bib_number']?>" aria-label="Edit leader bib"><button class="btn btn-sm btn-outline-primary">Save</button></form></td><td><?=e($x['display_name'])?><?php if($x['competitor_status']==='pending'):?> <span class="badge text-bg-warning">Details pending</span><?php endif;?></td><td><code><?=e($x['bdc_id'])?></code></td><td><form method="post"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="remove_entry"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="entry_id" value="<?=$x['id']?>"><button class="btn btn-sm btn-outline-danger">Remove</button></form></td></tr><?php endforeach;?></tbody></table>
</div></div></div>
<div class="col-lg-6"><div class="card shadow-sm role-card"><div class="card-header bg-danger-subtle fw-semibold">Followers</div><div class="card-body">
<form method="post" class="row g-2 mb-3"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="add_entry"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="dance_role" value="follower">
<div class="col-3"><input class="form-control" type="number" min="1" name="bib_number" value="<?=$nextBib['follower']?>" aria-label="Follower bib number" required><div class="form-text">Next suggested bib. You can overwrite it.</div></div>
<div class="col-9"><input class="form-control" name="competitor_search" list="competitorSuggestions" placeholder="Type competitor name or BDC ID" required><div class="form-text">Select an existing BDC ID, or type a new name and use Create Name &amp; Add.</div></div>
<div class="col-6"><button class="btn btn-danger w-100" name="entry_mode" value="existing">Add Existing</button></div>
<div class="col-6"><button class="btn btn-outline-danger w-100" name="entry_mode" value="create" onclick="return confirm('Create a provisional BDC competitor using only this name? The competitor can complete details later.')">Create Name &amp; Add</button></div>
</form>
<table class="table table-sm align-middle"><thead><tr><th style="width:150px">Bib</th><th>Competitor</th><th>BDC ID</th><th style="width:100px"></th></tr></thead><tbody><?php foreach($entries['follower'] as $x):?><tr><td><form method="post" class="d-flex gap-1"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="update_bib"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="entry_id" value="<?=$x['id']?>"><input class="form-control form-control-sm" style="width:76px" type="number" min="1" name="bib_number" value="<?=$x['bib_number']?>" aria-label="Edit follower bib"><button class="btn btn-sm btn-outline-primary">Save</button></form></td><td><?=e($x['display_name'])?><?php if($x['competitor_status']==='pending'):?> <span class="badge text-bg-warning">Details pending</span><?php endif;?></td><td><code><?=e($x['bdc_id'])?></code></td><td><form method="post"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="remove_entry"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="entry_id" value="<?=$x['id']?>"><button class="btn btn-sm btn-outline-danger">Remove</button></form></td></tr><?php endforeach;?></tbody></table>
</div></div></div>
</div></fieldset>
<div class="card shadow-sm mb-4 <?=$rosterSubmitted?'border-success bg-success-subtle':'border-warning bg-warning-subtle'?>"><div class="card-body d-flex justify-content-between align-items-center gap-3 flex-wrap"><div><h2 class="h5 mb-1">Test Competitor Checkpoint</h2><div class="small text-body-secondary"><?=$rosterSubmitted?'Competitors are submitted and locked. Manual score entry is open.':'Save the current Test roster as a draft, then submit and lock it before manual scoring.'?></div><?php if(!empty($rosterState['saved_at'])):?><div class="small mt-1">Last saved: <?=e((string)$rosterState['saved_at'])?></div><?php endif;?></div><div><?php if(!$rosterSubmitted):?><form method="post" class="d-flex gap-2"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><button class="btn btn-outline-dark" name="action" value="save_competitors">Save Competitors</button><button class="btn btn-success" name="action" value="submit_competitors" onclick="return confirm('Submit and lock the Test competitor roster? Bibs and competitors cannot be changed until an authorised reopen.')">Submit Competitors</button></form><?php elseif(Auth::isSuperAdmin()):?><form method="post" class="d-flex gap-2 flex-wrap"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input class="form-control" name="reopen_reason" placeholder="Required reopen reason" required><button class="btn btn-warning" name="action" value="reopen_competitors" onclick="return confirm('CAUTION: Reopen this locked Test roster? Manual scoring will be blocked until competitors are submitted again.')">Reopen Competitors</button></form><?php endif;?></div></div></div>
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
$tiePanelTest=true;
$tiePanelAction='';
$tiePanelAttributes='';
require dirname(__DIR__).'/scoring/tie-resolution-panel.php';
?>

<?php endif;?>
<?php endif;?><?php endif;?>
<?php if($round):$backupTestMode=true;require dirname(__DIR__).'/scoring/backup-panel.php';endif;?>
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
  +'<input class="form-control" name="final_judges['+key+'][name]" placeholder="Final judge name" required>'
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
}function addJudge(){const w=document.getElementById('judgesWrap');const i=w.querySelectorAll('.judge-row').length;const d=document.createElement('div');d.className='row g-2 mb-2 judge-row align-items-center';d.innerHTML='<div class="col-md-2"><strong>Judge '+(i+1)+'</strong></div><div class="col-md-5"><input class="form-control" name="judge_name[]" placeholder="Judge name" required></div><div class="col-md-3"><select class="form-select" name="judge_scope[]"><option value="all">All</option><option value="leader">Leaders</option><option value="follower">Followers</option></select></div><div class="col-md-2"><label><input type="radio" name="chief_index" value="'+i+'"> Chief</label></div>';w.appendChild(d);}
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
</script><script src="../../public/js/final-pairing-sync.js?v=386" defer></script><script src="../../public/js/final-score-sync.js?v=386" defer></script><script src="../../public/js/bdc-copy-link-v345.js?v=345"></script><script src="../../public/js/judge-order-controls.js?v=380"></script><script src="../../public/js/scoring-judge-directory.js?v=381"></script></body></html>
