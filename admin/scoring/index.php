<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\DivisionProgressionService;
use App\Services\SchemaUpdater;

Auth::requireAdmin();

$scoringMode=(string)($_GET['mode']??'');
if(
 $_SERVER['REQUEST_METHOD']==='GET'
 && !isset($_GET['round_id'])
 && !in_array($scoringMode,['manual'],true)
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
  <?php if($automatedSelected):?>
   <div class="alert alert-info text-center mb-4"><strong>Automated Scoring</strong> is planned for a future version.</div>
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
     <p class="flex-grow-1">Automated scoring will be introduced in a future version.</p>
     <a class="btn btn-outline-secondary btn-lg" href="?mode=automated">Future Version</a>
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
    $root=dirname(__DIR__,2).'/public/results';
    if(!is_dir($root) && !mkdir($root,0755,true) && !is_dir($root)) throw new RuntimeException('Could not create public/results.');
    return $root;
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
        $marks
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
        INSERT INTO bdc_scoring_judges(round_id,judge_name,judge_order,is_chief,scoring_scope)
        SELECT :new_round,judge_name,judge_order,is_chief,scoring_scope
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
      'active_child_entries'=>$actual
    ]);

    return $actual;
}

function createNextScoringRound(PDO $pdo,array $source,string $nextType,int $userId):int{
    if(!in_array($nextType,['semifinal','final'],true)) throw new RuntimeException('Invalid next round.');
    $pending=$pdo->prepare("
      SELECT COUNT(*) FROM (
       SELECT se.dance_role,sr.rank_number,sr.total_score,sr.chief_score
       FROM bdc_scoring_results sr
       JOIN bdc_scoring_entries se ON se.id=sr.entry_id
       WHERE sr.round_id=:r AND sr.result_status='tie_pending'
       GROUP BY se.dance_role,sr.rank_number,sr.total_score,sr.chief_score
       HAVING COUNT(*)>1
      ) unresolved_ties
    ");
    $pending->execute(['r'=>$source['id']]);
    if((int)$pending->fetchColumn()>0) throw new RuntimeException('Resolve all callback ties before proceeding.');

    $existing=$pdo->prepare("SELECT id FROM bdc_scoring_rounds WHERE event_id=:e AND division=:d AND round_type=:t AND status<>'archived' ORDER BY id DESC LIMIT 1");
    $existing->execute(['e'=>$source['event_id'],'d'=>$source['division'],'t'=>$nextType]);
    $existingId=(int)$existing->fetchColumn();
    if($existingId>0){
        $pdo->beginTransaction();
        try{
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
          event_id,parent_round_id,source_round_id,round_type,division,
          yes_count,callback_count,yes_weight,alt1_weight,alt2_weight,alt3_weight,
          status,created_by
        ) VALUES(:e,:p,:s,:t,:d,:yes,:cb,:yw,:a1,:a2,:a3,'draft',:u)");
        $insert->execute([
          'e'=>$source['event_id'],'p'=>$source['id'],'s'=>$source['id'],'t'=>$nextType,
          'd'=>$source['division'],'yes'=>$source['yes_count'],'cb'=>$source['callback_count'],
          'yw'=>$source['yes_weight'],'a1'=>$source['alt1_weight'],'a2'=>$source['alt2_weight'],
          'a3'=>$source['alt3_weight'],'u'=>$userId?:null
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
    $table=function(string $role,array $rows)use($judges){ob_start();?><table><thead><tr><th><?=strtoupper($role)==='LEADER'?'LEAD #':'FOLLOW #'?></th><th><?=strtoupper($role).'S'?></th><?php foreach($judges as $j):?><th>J<?= (int)$j['judge_order'] ?><?= (int)$j['is_chief']?'*':'' ?></th><?php endforeach;?><th>TOTAL</th><th>CB</th></tr></thead><tbody><?php foreach($rows as $r):?><tr class="<?=e((string)$r['result_status'])?>"><td><?= (int)$r['bib_number'] ?></td><td><?=e($r['display_name'])?></td><?php foreach($judges as $j):?><td></td><?php endforeach;?><td><?=number_format((float)$r['total_score'],1)?></td><td><?=($r['result_status']==='callback')?(int)$r['rank_number']:(($r['result_status']==='alternate')?'A'.(int)$r['alternate_rank']:'')?></td></tr><?php endforeach;?></tbody></table><?php return ob_get_clean();};
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
   if($lockedRound && in_array((string)$lockedRound['status'],['pending_approval','archived'],true)){
    $message=$lockedRound['status']==='pending_approval'
      ? 'This competition is pending Super Admin approval and is temporarily read-only.'
      : 'This competition is archived and read-only. Only Super Admin rollback can reopen it.';
    throw new RuntimeException($message);
   }
  }
  if($action==='create_round'){
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
   $existing=$pdo->prepare("SELECT id FROM bdc_scoring_rounds WHERE event_id=:e AND division=:d AND round_type=:rt AND status<>'archived' ORDER BY id DESC LIMIT 1");
   $existing->execute(['e'=>$eventId,'d'=>$division,'rt'=>$roundType]);
   $existingId=(int)$existing->fetchColumn();
   if($existingId>0){$roundId=$existingId;$notice=ucfirst($roundType).' round already exists. Existing round opened.';}
   else{
    $s=$pdo->prepare("INSERT INTO bdc_scoring_rounds(event_id,round_type,division,yes_count,callback_count,yes_weight,alt1_weight,alt2_weight,alt3_weight,created_by) VALUES(:e,:rt,:d,10,10,10.00,4.50,4.30,4.20,:u)");
    $s->execute(['e'=>$eventId,'rt'=>$roundType,'d'=>$division,'u'=>$userId]);
    $roundId=(int)$pdo->lastInsertId();
    auditScoring($pdo,$roundId,$userId,'round_created',['round_type'=>$roundType,'new_event'=>$newEventName!=='']);
    $deskLink=ensureRegistrationDeskLink($pdo,$eventId,$division,$userId);
    if(!empty($deskLink['plain_token']))$_SESSION['registration_desk_tokens'][(int)$deskLink['id']]=$deskLink['plain_token'];
    $notice=ucfirst($roundType).' round created. Registration Desk link is ready below.';
   }
  }elseif($action==='delete_scoring_workflow'){
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
    FROM bdc_scoring_publications p
    JOIN bdc_scoring_rounds r ON r.id=p.final_round_id
    WHERE r.event_id=:e AND r.division=:d AND p.status='published'
   ");
   $published->execute(['e'=>$eventId,'d'=>$division]);
   if((int)$published->fetchColumn()>0){
    throw new RuntimeException('This workflow is published. Use Super Admin rollback before deleting it.');
   }

   $roundStmt=$pdo->prepare("SELECT id FROM bdc_scoring_rounds WHERE event_id=:e AND division=:d ORDER BY id");
   $roundStmt->execute(['e'=>$eventId,'d'=>$division]);
   $ids=array_map('intval',$roundStmt->fetchAll(PDO::FETCH_COLUMN));
   if(!$ids)throw new RuntimeException('No scoring rounds found.');

   $ph=implode(',',array_fill(0,count($ids),'?'));
   $pdo->beginTransaction();
   try{
    $pairStmt=$pdo->prepare("SELECT id FROM bdc_scoring_final_pairs WHERE round_id IN ($ph)");
    $pairStmt->execute($ids);
    $pairIds=array_map('intval',$pairStmt->fetchAll(PDO::FETCH_COLUMN));
    if($pairIds){
     $pph=implode(',',array_fill(0,count($pairIds),'?'));
     $pdo->prepare("DELETE FROM bdc_scoring_final_results WHERE pair_id IN ($pph)")->execute($pairIds);
     $pdo->prepare("DELETE FROM bdc_scoring_final_marks WHERE pair_id IN ($pph)")->execute($pairIds);
    }

    foreach([
     'bdc_scoring_final_results',
     'bdc_scoring_final_marks',
     'bdc_scoring_final_pairs',
     'bdc_scoring_results',
     'bdc_scoring_marks',
     'bdc_scoring_judges',
     'bdc_scoring_entries',
     'bdc_scoring_audit'
    ] as $table){
     $pdo->prepare("DELETE FROM {$table} WHERE round_id IN ($ph)")->execute($ids);
    }

    $pubStmt=$pdo->prepare("
      SELECT p.id
      FROM bdc_scoring_publications p
      JOIN bdc_scoring_rounds r ON r.id=p.final_round_id
      WHERE r.event_id=? AND r.division=? AND p.status='rolled_back'
    ");
    $pubStmt->execute([$eventId,$division]);
    $pubIds=array_map('intval',$pubStmt->fetchAll(PDO::FETCH_COLUMN));
    if($pubIds){
     $pubPh=implode(',',array_fill(0,count($pubIds),'?'));
     $pdo->prepare("DELETE FROM bdc_scoring_publication_points WHERE publication_id IN ($pubPh)")->execute($pubIds);
     $pdo->prepare("DELETE FROM bdc_scoring_publications WHERE id IN ($pubPh)")->execute($pubIds);
    }

    $pdo->prepare("DELETE FROM bdc_scoring_rounds WHERE id IN ($ph)")->execute($ids);
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
   $pdo->prepare('UPDATE bdc_scoring_rounds SET yes_count=:y,callback_count=:c,tier_manual_override=1,yes_weight=10.00,alt1_weight=4.50,alt2_weight=4.30,alt3_weight=4.20 WHERE id=:id')->execute(['y'=>$yes,'c'=>$yes,'id'=>$roundId]);
   auditScoring($pdo,$roundId,$userId,'heats_settings_saved',['tier'=>$tier,'yes_count'=>$yes,'alternate_count'=>3,'weights'=>['yes'=>10.0,'alt1'=>4.5,'alt2'=>4.3,'alt3'=>4.2]]);
   $notice='BDC Tier '.$tier.' settings saved: '.$yes.' YES selections and 3 alternates.';
  }elseif($action==='add_entry'){
   $roundId=(int)$_POST['round_id'];$role=(string)$_POST['dance_role'];$bib=(int)$_POST['bib_number'];$term=trim((string)$_POST['competitor_search']);$entryMode=(string)($_POST['entry_mode']??'existing');
   $overrideDivision=isset($_POST['override_division']) && (string)$_POST['override_division']==='1';
   $overrideReason=trim((string)($_POST['override_reason']??''));
   if(!in_array($role,['leader','follower'],true)||$bib<1||$term==='')throw new RuntimeException('Choose role, bib and competitor name.');
   $roundForEntry=loadRound($pdo,$roundId);if(!$roundForEntry)throw new RuntimeException('Round not found.');
   $bibCheck=$pdo->prepare("SELECT se.id,se.display_name FROM bdc_scoring_entries se WHERE se.round_id=:r AND se.dance_role=:role AND se.bib_number=:bib AND se.entry_status='active' LIMIT 1");
   $bibCheck->execute(['r'=>$roundId,'role'=>$role,'bib'=>$bib]);$bibTaken=$bibCheck->fetch();
   if($bibTaken)throw new RuntimeException('Bib '.$bib.' is already assigned to '.$bibTaken['display_name'].' on the '.ucfirst($role).' side.');
   $selectedBdc='';
   if(preg_match('/^(BDC-\d+)/i',$term,$m))$selectedBdc=strtoupper($m[1]);
   $comp=null;
   if($entryMode!=='create'){
    $c=$pdo->prepare("SELECT id,bdc_id,exact_name,dance_role,current_division,status,novice_manual_out,intermediate_manual_out FROM bdc_competitors WHERE (bdc_id=:bdc OR id=:num OR LOWER(exact_name)=LOWER(:exact)) AND dance_role IN(:role,'both') ORDER BY CASE WHEN dance_role=:preferred THEN 0 ELSE 1 END,id LIMIT 1");
    $c->execute(['bdc'=>$selectedBdc!==''?$selectedBdc:$term,'num'=>ctype_digit($term)?(int)$term:0,'exact'=>$term,'role'=>$role,'preferred'=>$role]);$comp=$c->fetch()?:null;
    if(!$comp){
     $c=$pdo->prepare("SELECT id,bdc_id,exact_name,dance_role,current_division,status,novice_manual_out,intermediate_manual_out FROM bdc_competitors WHERE exact_name LIKE :like AND dance_role IN(:role,'both') ORDER BY exact_name,id LIMIT 2");
     $c->execute(['like'=>'%'.$term.'%','role'=>$role]);$matches=$c->fetchAll();
     if(count($matches)===1)$comp=$matches[0];
     elseif(count($matches)>1)throw new RuntimeException('Several competitors match this name. Select the correct BDC ID from the suggestions.');
    }
   }
   if(!$comp){
    if($entryMode!=='create')throw new RuntimeException('Competitor not found in the BDC database. To add a dancer whose BDC record or points are not updated, use Add Non-BDC / Not Updated and provide an override reason.');
    if(!$overrideDivision || $overrideReason==='')throw new RuntimeException('This competitor is not confirmed in the BDC database. Tick Add Anyway and enter the reason for the override.');
    $normalised=strtolower(trim((string)preg_replace('/\s+/',' ',$term)));
    $existingName=$pdo->prepare("SELECT id,bdc_id,exact_name FROM bdc_competitors WHERE normalised_name=:n ORDER BY id LIMIT 1");
    $existingName->execute(['n'=>$normalised]);$same=$existingName->fetch();
    if($same)throw new RuntimeException('A competitor with this name already exists: '.$same['exact_name'].' ('.$same['bdc_id'].'). Select the existing record.');
    $pdo->beginTransaction();
    try{
     $next=(int)$pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(bdc_id,5) AS UNSIGNED)),0)+1 FROM bdc_competitors WHERE bdc_id LIKE 'BDC-%'")->fetchColumn();
     $bdcId='BDC-'.str_pad((string)$next,6,'0',STR_PAD_LEFT);
     $ins=$pdo->prepare("INSERT INTO bdc_competitors(bdc_id,exact_name,normalised_name,dance_role,current_division,status,is_historical) VALUES(:bdc,:name,:normalised,:role,:division,'pending',0)");
     $ins->execute(['bdc'=>$bdcId,'name'=>$term,'normalised'=>$normalised,'role'=>$role,'division'=>$roundForEntry['division']]);
     $comp=['id'=>(int)$pdo->lastInsertId(),'bdc_id'=>$bdcId,'exact_name'=>$term,'current_division'=>$roundForEntry['division'],'status'=>'pending'];
     $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
   }
   $competitorDivision=(string)($comp['current_division']??'unknown');
   $divisionMismatch=false;
   if($entryMode!=='create'){
    $pointStmt=$pdo->prepare("SELECT
      COALESCE(SUM(CASE WHEN division='novice' THEN points ELSE 0 END),0) novice_points,
      COALESCE(SUM(CASE WHEN division='intermediate' THEN points ELSE 0 END),0) intermediate_points,
      COALESCE(SUM(CASE WHEN division='advanced' THEN points ELSE 0 END),0) advanced_points
     FROM bdc_point_transactions WHERE competitor_id=:competitor AND dance_role IN(:role,'both')");
    $pointStmt->execute(['competitor'=>$comp['id'],'role'=>$role]);$points=$pointStmt->fetch()?:[];
    $historyStmt=$pdo->prepare("SELECT
      MAX(CASE WHEN division='intermediate' THEN 1 ELSE 0 END) competed_intermediate,
      MAX(CASE WHEN division='advanced' THEN 1 ELSE 0 END) competed_advanced,
      MAX(CASE WHEN division='all_star' THEN 1 ELSE 0 END) competed_all_star
     FROM (
      SELECT division FROM bdc_participant_results WHERE competitor_id=:participant AND dance_role IN(:participant_role,'both')
      UNION ALL
      SELECT division FROM bdc_point_transactions WHERE competitor_id=:transaction AND dance_role IN(:transaction_role,'both')
     ) history");
    $historyStmt->execute(['participant'=>$comp['id'],'participant_role'=>$role,'transaction'=>$comp['id'],'transaction_role'=>$role]);$history=$historyStmt->fetch()?:[];
    $eligibility=DivisionProgressionService::eligibilityFor(
     (string)$roundForEntry['division'],
     (float)($points['novice_points']??0),(float)($points['intermediate_points']??0),(float)($points['advanced_points']??0),
     (string)($comp['current_division']??'unknown'),
     !empty($history['competed_intermediate']),!empty($history['competed_advanced']),!empty($history['competed_all_star'])
    );
    $divisionMismatch=!$eligibility['eligible'];
    if($divisionMismatch){
     throw new RuntimeException('Cannot add '.$comp['exact_name'].' to '.DivisionProgressionService::label((string)$roundForEntry['division']).': '.$eligibility['reason'].' Known BDC competitors cannot bypass division eligibility. Update their official points or competition history first.');
    }
   }
   $pdo->prepare("INSERT INTO bdc_scoring_entries(round_id,competitor_id,dance_role,bib_number,display_name) VALUES(:r,:c,:role,:bib,:n) ON DUPLICATE KEY UPDATE bib_number=VALUES(bib_number),display_name=VALUES(display_name),entry_status='active'")->execute(['r'=>$roundId,'c'=>$comp['id'],'role'=>$role,'bib'=>$bib,'n'=>$comp['exact_name']]);
   auditScoring($pdo,$roundId,$userId,'entry_added',['competitor_id'=>$comp['id'],'bdc_id'=>$comp['bdc_id'],'role'=>$role,'bib'=>$bib,'provisional'=>$entryMode==='create','division'=>$competitorDivision,'division_override'=>$entryMode==='create','override_reason'=>$entryMode==='create'?$overrideReason:null]);
   $notice=ucfirst($role).' added: '.$comp['exact_name'].' ('.$comp['bdc_id'].').';
  }elseif($action==='update_bib'){
   $roundId=(int)($_POST['round_id']??0);$entryId=(int)($_POST['entry_id']??0);$newBib=(int)($_POST['bib_number']??0);
   if($roundId<1||$entryId<1||$newBib<1)throw new RuntimeException('Enter a valid bib number.');
   $entryStmt=$pdo->prepare("SELECT id,dance_role,bib_number,display_name FROM bdc_scoring_entries WHERE id=:id AND round_id=:r AND entry_status='active'");
   $entryStmt->execute(['id'=>$entryId,'r'=>$roundId]);$entry=$entryStmt->fetch();
   if(!$entry)throw new RuntimeException('Scoring entry not found.');
   $duplicate=$pdo->prepare("SELECT display_name FROM bdc_scoring_entries WHERE round_id=:r AND dance_role=:role AND bib_number=:bib AND entry_status='active' AND id<>:id LIMIT 1");
   $duplicate->execute(['r'=>$roundId,'role'=>$entry['dance_role'],'bib'=>$newBib,'id'=>$entryId]);$takenBy=$duplicate->fetchColumn();
   if($takenBy)throw new RuntimeException('Bib '.$newBib.' is already assigned to '.$takenBy.' on the '.ucfirst($entry['dance_role']).' side.');
   $pdo->prepare("UPDATE bdc_scoring_entries SET bib_number=:bib WHERE id=:id AND round_id=:r")->execute(['bib'=>$newBib,'id'=>$entryId,'r'=>$roundId]);
   auditScoring($pdo,$roundId,$userId,'bib_updated',['entry_id'=>$entryId,'role'=>$entry['dance_role'],'old_bib'=>(int)$entry['bib_number'],'new_bib'=>$newBib]);
   $notice=$entry['display_name'].' bib updated to '.$newBib.'.';
  }elseif($action==='remove_entry'){
   $roundId=(int)$_POST['round_id'];$id=(int)$_POST['entry_id'];$pdo->prepare("UPDATE bdc_scoring_entries SET entry_status='withdrawn' WHERE id=:id AND round_id=:r")->execute(['id'=>$id,'r'=>$roundId]);auditScoring($pdo,$roundId,$userId,'entry_removed',['entry_id'=>$id]);$notice='Entry removed.';
  }elseif($action==='save_judges'){
   $roundId=(int)$_POST['round_id'];$rawNames=$_POST['judge_name']??[];$rawScopes=$_POST['judge_scope']??[];$chief=(int)($_POST['chief_index']??-1);$rows=[];
   foreach($rawNames as $index=>$rawName){$name=trim((string)$rawName);if($name==='')continue;$scope=(string)($rawScopes[$index]??'all');if(!in_array($scope,['all','leader','follower'],true))$scope='all';$rows[]=['name'=>$name,'scope'=>$scope,'original_index'=>(int)$index];}
   if(count($rows)<3)throw new RuntimeException('Minimum 3 judges required.');
   if(count($rows)!==count(array_unique(array_map(fn($row)=>mb_strtolower($row['name']),$rows))))throw new RuntimeException('Judge names must be unique.');
   $chiefRowIndex=null;foreach($rows as $rowIndex=>$row){if($row['original_index']===$chief){$chiefRowIndex=$rowIndex;break;}}if($chiefRowIndex===null)throw new RuntimeException('Select one Chief Judge.');
   foreach(['leader','follower'] as $role){$panelCount=count(array_filter($rows,fn($row)=>in_array($row['scope'],['all',$role],true)));if($panelCount<3)throw new RuntimeException(ucfirst($role).' panel must have at least 3 judges.');}
   $existingStmt=$pdo->prepare("SELECT * FROM bdc_scoring_judges WHERE round_id=:r ORDER BY judge_order");$existingStmt->execute(['r'=>$roundId]);$existing=$existingStmt->fetchAll();$existingByName=[];foreach($existing as $judge)$existingByName[mb_strtolower(trim((string)$judge['judge_name']))]=$judge;
   $pdo->beginTransaction();
   try{
    $usedIds=[];$chiefId=0;$update=$pdo->prepare("UPDATE bdc_scoring_judges SET judge_name=:name,judge_order=:order_no,is_chief=:chief,scoring_scope=:scope WHERE id=:id AND round_id=:round");$insert=$pdo->prepare("INSERT INTO bdc_scoring_judges(round_id,judge_name,judge_order,is_chief,scoring_scope) VALUES(:round,:name,:order_no,:chief,:scope)");
    foreach($rows as $index=>$row){$key=mb_strtolower($row['name']);$isChief=$index===$chiefRowIndex?1:0;if(isset($existingByName[$key])){$id=(int)$existingByName[$key]['id'];$update->execute(['name'=>$row['name'],'order_no'=>$index+1,'chief'=>$isChief,'scope'=>$row['scope'],'id'=>$id,'round'=>$roundId]);}else{$insert->execute(['round'=>$roundId,'name'=>$row['name'],'order_no'=>$index+1,'chief'=>$isChief,'scope'=>$row['scope']]);$id=(int)$pdo->lastInsertId();}$usedIds[]=$id;if($isChief)$chiefId=$id;}
    if($usedIds){$ph=implode(',',array_fill(0,count($usedIds),'?'));$pdo->prepare("DELETE FROM bdc_scoring_judges WHERE round_id=? AND id NOT IN ($ph)")->execute(array_merge([$roundId],$usedIds));}
    $pdo->prepare('UPDATE bdc_scoring_rounds SET chief_judge_id=:chief WHERE id=:round')->execute(['chief'=>$chiefId,'round'=>$roundId]);auditScoring($pdo,$roundId,$userId,'judges_saved',['count'=>count($rows),'chief'=>$rows[$chiefRowIndex]['name'],'scopes'=>array_count_values(array_column($rows,'scope'))]);$pdo->commit();$notice='Judges saved. Existing scores for unchanged judges were preserved.';
   }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
  }elseif($action==='save_round_officials'){
   $roundId=(int)($_POST['round_id']??0);
   $roundForOfficials=loadRound($pdo,$roundId);
   if(!$roundForOfficials)throw new RuntimeException('Round not found.');
   $administrator=trim((string)($_POST['scoring_administrator']??''));
   $w1=trim((string)($_POST['witness_1']??''));
   $w2=trim((string)($_POST['witness_2']??''));
   $w3=trim((string)($_POST['witness_3']??''));
   $pdo->prepare("UPDATE bdc_scoring_rounds SET scoring_administrator=NULLIF(:admin,''),witness_1=NULLIF(:w1,''),witness_2=NULLIF(:w2,''),witness_3=NULLIF(:w3,'') WHERE id=:r")
       ->execute(['admin'=>$administrator,'w1'=>$w1,'w2'=>$w2,'w3'=>$w3,'r'=>$roundId]);
   auditScoring($pdo,$roundId,$userId,'round_officials_saved',['scoring_administrator'=>$administrator]);
   $notice='Officials and scoring witnesses saved.';

  }elseif($action==='save_scores' || $action==='calculate_scores' || $action==='submit_scores'){
   @set_time_limit(180);
   $roundId=(int)$_POST['round_id'];$round=loadRound($pdo,$roundId);if(!$round)throw new RuntimeException('Round not found.');
   $postedMarks=$_POST['mark']??[];
   if(!empty($_POST['score_payload'])){
    $decodedMarks=json_decode((string)$_POST['score_payload'],true);
    if(!is_array($decodedMarks))throw new RuntimeException('The score payload could not be read.');
    $postedMarks=$decodedMarks;
   }
   $witnesses=[
    trim((string)($_POST['witness_1']??'')),
    trim((string)($_POST['witness_2']??'')),
    trim((string)($_POST['witness_3']??''))
   ];
   $scoringAdministrator=trim((string)($_POST['scoring_administrator']??''));
   $pdo->prepare("UPDATE bdc_scoring_rounds SET witness_1=NULLIF(:w1,''),witness_2=NULLIF(:w2,''),witness_3=NULLIF(:w3,''),scoring_administrator=NULLIF(:admin,'') WHERE id=:r")
       ->execute(['w1'=>$witnesses[0],'w2'=>$witnesses[1],'w3'=>$witnesses[2],'admin'=>$scoringAdministrator,'r'=>$roundId]);
   $round=loadRound($pdo,$roundId);$judges=$pdo->prepare('SELECT * FROM bdc_scoring_judges WHERE round_id=:r');$judges->execute(['r'=>$roundId]);$judges=$judges->fetchAll();$validJudge=array_column($judges,'id');$judgeById=[];foreach($judges as $judge)$judgeById[(int)$judge['id']]=$judge;$entryRoleStmt=$pdo->prepare("SELECT dance_role FROM bdc_scoring_entries WHERE id=:id AND round_id=:round");$up=$pdo->prepare("INSERT INTO bdc_scoring_marks(round_id,entry_id,judge_id,mark_type,alt_rank,weighted_score,updated_by) VALUES(:r,:e,:j,:t,:a,:w,:u) ON DUPLICATE KEY UPDATE mark_type=VALUES(mark_type),alt_rank=VALUES(alt_rank),weighted_score=VALUES(weighted_score),updated_by=VALUES(updated_by),updated_at=NOW()");
   foreach($postedMarks as $entryId=>$marks){$entryRoleStmt->execute(['id'=>(int)$entryId,'round'=>$roundId]);$entryRole=(string)$entryRoleStmt->fetchColumn();foreach($marks as $judgeId=>$raw){if(!in_array((int)$judgeId,array_map('intval',$validJudge),true))continue;$scope=(string)(($judgeById[(int)$judgeId]['scoring_scope']??'all'));if($scope!=='all'&&$scope!==$entryRole)continue;$raw=trim((string)$raw);$type='blank';$alt=null;$weight=0.0;if($raw==='1'||strtolower($raw)==='y'||strtolower($raw)==='yes'){$type='yes';$weight=(float)$round['yes_weight'];}elseif(in_array($raw,['A1','a1','2'],true)){$type='alt';$alt=1;$weight=(float)$round['alt1_weight'];}elseif(in_array($raw,['A2','a2','3'],true)){$type='alt';$alt=2;$weight=(float)$round['alt2_weight'];}elseif(in_array($raw,['A3','a3','4'],true)){$type='alt';$alt=3;$weight=(float)$round['alt3_weight'];}$up->execute(['r'=>$roundId,'e'=>(int)$entryId,'j'=>(int)$judgeId,'t'=>$type,'a'=>$alt,'w'=>$weight,'u'=>$userId]);}}
   if($action==='submit_scores'){
    $calcStarted=microtime(true);$memoryBefore=memory_get_usage(true);
    computeResults($pdo,$round,$userId);
    $calcMs=(int)round((microtime(true)-$calcStarted)*1000);$calcMemory=max(0,memory_get_peak_usage(true)-$memoryBefore);
    $pdo->prepare("UPDATE bdc_scoring_rounds SET last_calculation_ms=:ms,last_calculation_memory_bytes=:memory WHERE id=:round")->execute(['ms'=>$calcMs,'memory'=>$calcMemory,'round'=>$roundId]);
    $notice='Scores submitted in '.$calcMs.' ms. Callback results are saved. Choose Semifinal or Final below.';
   }elseif($action==='calculate_scores'){
    $calcStarted=microtime(true);$memoryBefore=memory_get_usage(true);
    computeResults($pdo,$round,$userId);
    $calcMs=(int)round((microtime(true)-$calcStarted)*1000);$calcMemory=max(0,memory_get_peak_usage(true)-$memoryBefore);
    $pdo->prepare("UPDATE bdc_scoring_rounds SET last_calculation_ms=:ms,last_calculation_memory_bytes=:memory WHERE id=:round")->execute(['ms'=>$calcMs,'memory'=>$calcMemory,'round'=>$roundId]);
    $pdo->prepare("UPDATE bdc_scoring_rounds SET status='draft' WHERE id=:r")->execute(['r'=>$roundId]);
    auditScoring($pdo,$roundId,$userId,'calculate_and_sort_preview');
    $notice='Calculated and sorted result is ready for review in '.$calcMs.' ms. The round remains editable.';
   }else{
    $pdo->prepare("UPDATE bdc_scoring_rounds SET status='draft' WHERE id=:r")->execute(['r'=>$roundId]);
    auditScoring($pdo,$roundId,$userId,'save_scores');
    $notice='Draft saved. Screen data retained.';
   }
  }elseif($action==='resolve_callback_tie'){
   $roundId=(int)($_POST['round_id']??0);
   $selectedEntryId=(int)($_POST['selected_entry_id']??0);
   if($roundId<1||$selectedEntryId<1)throw new RuntimeException('Select a tied competitor.');

   $selectedStmt=$pdo->prepare("
    SELECT sr.*,se.dance_role,se.display_name,se.bib_number
    FROM bdc_scoring_results sr
    JOIN bdc_scoring_entries se ON se.id=sr.entry_id
    WHERE sr.round_id=:r AND sr.entry_id=:e AND sr.result_status='tie_pending'
    LIMIT 1
   ");
   $selectedStmt->execute(['r'=>$roundId,'e'=>$selectedEntryId]);
   $selected=$selectedStmt->fetch();
   if(!$selected)throw new RuntimeException('This competitor is no longer in an unresolved callback tie.');

   $groupStmt=$pdo->prepare("
    SELECT sr.*,se.display_name,se.bib_number
    FROM bdc_scoring_results sr
    JOIN bdc_scoring_entries se ON se.id=sr.entry_id
    WHERE sr.round_id=:r
      AND se.dance_role=:role
      AND sr.result_status='tie_pending'
      AND sr.rank_number=:rank
      AND ABS(sr.total_score-:total)<0.0001
      AND ABS(sr.chief_score-:chief)<0.0001
    ORDER BY se.bib_number,se.id
   ");
   $groupStmt->execute([
    'r'=>$roundId,
    'role'=>$selected['dance_role'],
    'rank'=>$selected['rank_number'],
    'total'=>$selected['total_score'],
    'chief'=>$selected['chief_score']
   ]);
   $group=$groupStmt->fetchAll();
   if(count($group)<2)throw new RuntimeException('The unresolved tie group could not be found.');

   $roundForTie=loadRound($pdo,$roundId);
   if(!$roundForTie)throw new RuntimeException('Round not found.');
   $callbackLimit=(int)$roundForTie['callback_count'];

   $pdo->beginTransaction();
   try{
    $update=$pdo->prepare("
     UPDATE bdc_scoring_results
     SET result_status=:status,
         rank_number=:rank,
         alternate_rank=:alternate_rank,
         updated_at=NOW()
     WHERE round_id=:round_id AND entry_id=:entry_id
    ");

    // Chief Judge-selected competitor receives the final callback place.
    $update->execute([
     'status'=>'callback',
     'rank'=>$callbackLimit,
     'alternate_rank'=>null,
     'round_id'=>$roundId,
     'entry_id'=>$selectedEntryId
    ]);

    // Remaining tied competitors become ALT 1-3, then eliminated.
    $alternateRank=1;
    $displayRank=$callbackLimit+1;
    foreach($group as $candidate){
     if((int)$candidate['entry_id']===$selectedEntryId)continue;
     if($alternateRank<=3){
      $update->execute([
       'status'=>'alternate',
       'rank'=>$displayRank++,
       'alternate_rank'=>$alternateRank++,
       'round_id'=>$roundId,
       'entry_id'=>(int)$candidate['entry_id']
      ]);
     }else{
      $update->execute([
       'status'=>'eliminated',
       'rank'=>$displayRank++,
       'alternate_rank'=>null,
       'round_id'=>$roundId,
       'entry_id'=>(int)$candidate['entry_id']
      ]);
     }
    }

    auditScoring($pdo,$roundId,$userId,'callback_tie_resolved',[
     'role'=>$selected['dance_role'],
     'selected_entry_id'=>$selectedEntryId,
     'selected_name'=>$selected['display_name'],
     'selected_bib'=>(int)$selected['bib_number'],
     'tie_rank'=>(int)$selected['rank_number']
    ]);
    $pdo->commit();
    $notice='Tie resolved. '.$selected['display_name'].' was selected by the Chief Judge as the callback.';
   }catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    throw $e;
   }
  }elseif($action==='create_next_round'){
   $roundId=(int)($_POST['round_id']??0);
   $nextType=(string)($_POST['next_round_type']??'');
   $source=loadRound($pdo,$roundId);
   if(!$source)throw new RuntimeException('Source round not found.');
   if(!in_array($source['status'],['awaiting_decision','scores_submitted'],true))throw new RuntimeException('Submit scores before proceeding.');
   $roundId=createNextScoringRound($pdo,$source,$nextType,$userId);
   $tierInfo=applyAutomaticTier($pdo,$roundId,true);
   $movedStmt=$pdo->prepare("SELECT dance_role,COUNT(*) total FROM bdc_scoring_entries WHERE round_id=:r AND entry_status='active' GROUP BY dance_role");
   $movedStmt->execute(['r'=>$roundId]);
   $moved=['leader'=>0,'follower'=>0];
   foreach($movedStmt->fetchAll() as $movedRow)$moved[$movedRow['dance_role']]=(int)$movedRow['total'];
   $notice=ucfirst($nextType).' round opened with '.$moved['leader'].' Leaders and '.$moved['follower'].' Followers. Automatic Tier '.$tierInfo['tier'].' uses the larger individual role count of '.$tierInfo['largest'].'.';
  }elseif($action==='cancel_child_round'){
   $roundId=(int)($_POST['round_id']??0);
   $child=loadRound($pdo,$roundId);
   if(!$child||!(int)$child['parent_round_id'])throw new RuntimeException('This round cannot be cancelled.');
   $parentId=(int)$child['parent_round_id'];
   $pdo->beginTransaction();
   try{
    $pdo->prepare("DELETE FROM bdc_scoring_final_pairs WHERE round_id=:r")->execute(['r'=>$roundId]);
    $pdo->prepare("DELETE FROM bdc_scoring_results WHERE round_id=:r")->execute(['r'=>$roundId]);
    $pdo->prepare("DELETE FROM bdc_scoring_marks WHERE round_id=:r")->execute(['r'=>$roundId]);
    $pdo->prepare("DELETE FROM bdc_scoring_judges WHERE round_id=:r")->execute(['r'=>$roundId]);
    $pdo->prepare("DELETE FROM bdc_scoring_entries WHERE round_id=:r")->execute(['r'=>$roundId]);
    $pdo->prepare("DELETE FROM bdc_scoring_rounds WHERE id=:r")->execute(['r'=>$roundId]);
    $pdo->prepare("UPDATE bdc_scoring_rounds SET status='awaiting_decision' WHERE id=:r")
        ->execute(['r'=>$parentId]);
    auditScoring($pdo,$parentId,$userId,'child_round_cancelled',['cancelled_round_id'=>$roundId]);
    $pdo->commit();
    $roundId=$parentId;
    $notice='Next-round draft cancelled. Previous round reopened with all scores preserved.';
   }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
  }elseif($action==='add_next_finalist'){
   $roundId=(int)($_POST['round_id']??0);
   $role=(string)($_POST['dance_role']??'');
   if(!in_array($role,['leader','follower'],true))throw new RuntimeException('Invalid finalist role.');
   $finalRound=loadRound($pdo,$roundId);
   if(!$finalRound||$finalRound['round_type']!=='final')throw new RuntimeException('Final round not found.');
   $sourceRoundId=(int)($finalRound['source_round_id']?:$finalRound['parent_round_id']);
   if($sourceRoundId<1)throw new RuntimeException('Previous round not found.');

   $nextStmt=$pdo->prepare("
    SELECT se.competitor_id,se.dance_role,se.bib_number,se.display_name,sr.rank_number,sr.total_score
    FROM bdc_scoring_entries se
    JOIN bdc_scoring_results sr ON sr.round_id=se.round_id AND sr.entry_id=se.id
    WHERE se.round_id=:source_round
      AND se.dance_role=:role
      AND se.entry_status='active'
      AND NOT EXISTS(
       SELECT 1 FROM bdc_scoring_entries final_entry
       WHERE final_entry.round_id=:final_round
         AND final_entry.competitor_id=se.competitor_id
         AND final_entry.dance_role=se.dance_role
         AND final_entry.entry_status='active'
      )
    ORDER BY sr.rank_number ASC,sr.total_score DESC,se.bib_number ASC
    LIMIT 1
   ");
   $nextStmt->execute(['source_round'=>$sourceRoundId,'role'=>$role,'final_round'=>$roundId]);
   $candidate=$nextStmt->fetch();
   if(!$candidate)throw new RuntimeException('No additional ranked '.ucfirst($role).' is available.');

   $pdo->prepare("
    INSERT INTO bdc_scoring_entries(round_id,competitor_id,dance_role,bib_number,display_name,entry_status)
    VALUES(:r,:c,:role,:bib,:name,'active')
   ")->execute([
    'r'=>$roundId,'c'=>$candidate['competitor_id'],'role'=>$candidate['dance_role'],
    'bib'=>$candidate['bib_number'],'name'=>$candidate['display_name']
   ]);
   auditScoring($pdo,$roundId,$userId,'extra_finalist_added',[
    'role'=>$role,'competitor_id'=>(int)$candidate['competitor_id'],
    'source_rank'=>(int)$candidate['rank_number']
   ]);
   $notice='Added next ranked '.ucfirst($role).': '.$candidate['display_name'].'.';

  }elseif($action==='remove_finalist'){
   $roundId=(int)($_POST['round_id']??0);
   $entryId=(int)($_POST['entry_id']??0);
   $entryStmt=$pdo->prepare("SELECT * FROM bdc_scoring_entries WHERE id=:id AND round_id=:r AND entry_status='active'");
   $entryStmt->execute(['id'=>$entryId,'r'=>$roundId]);
   $entry=$entryStmt->fetch();
   if(!$entry)throw new RuntimeException('Finalist not found.');

   $pairStmt=$pdo->prepare("SELECT id FROM bdc_scoring_final_pairs WHERE round_id=:r AND (leader_entry_id=:e OR follower_entry_id=:e)");
   $pairStmt->execute(['r'=>$roundId,'e'=>$entryId]);
   $pairIds=array_map('intval',$pairStmt->fetchAll(PDO::FETCH_COLUMN));

   $pdo->beginTransaction();
   try{
    if($pairIds){
     $placeholders=implode(',',array_fill(0,count($pairIds),'?'));
     $pdo->prepare("DELETE FROM bdc_scoring_final_marks WHERE pair_id IN ($placeholders)")->execute($pairIds);
     $pdo->prepare("DELETE FROM bdc_scoring_final_results WHERE pair_id IN ($placeholders)")->execute($pairIds);
     $pdo->prepare("DELETE FROM bdc_scoring_final_pairs WHERE id IN ($placeholders)")->execute($pairIds);
    }
    $pdo->prepare("UPDATE bdc_scoring_entries SET entry_status='withdrawn' WHERE id=:id AND round_id=:r")
        ->execute(['id'=>$entryId,'r'=>$roundId]);
    auditScoring($pdo,$roundId,$userId,'finalist_removed',[
      'entry_id'=>$entryId,'role'=>$entry['dance_role'],'name'=>$entry['display_name']
    ]);
    $pdo->commit();
    $notice=$entry['display_name'].' removed from Final only. Previous-round result remains unchanged.';
   }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}

  }elseif($action==='save_final_judges'){
   $roundId=(int)($_POST['round_id']??0);
   $finalRound=loadRound($pdo,$roundId);
   if(!$finalRound||$finalRound['round_type']!=='final')throw new RuntimeException('Final round not found.');

   $rows=$_POST['final_judges']??[];
   $chiefKey=(string)($_POST['final_chief_key']??'');
   $clean=[];
   foreach($rows as $key=>$row){
    $name=trim((string)($row['name']??''));
    if($name==='')continue;
    $id=(int)($row['id']??0);
    $clean[(string)$key]=['id'=>$id,'name'=>$name];
   }
   if(count($clean)<3)throw new RuntimeException('Minimum 3 Final judges required.');
   $lower=array_map(fn($row)=>mb_strtolower($row['name']),array_values($clean));
   if(count($lower)!==count(array_unique($lower)))throw new RuntimeException('Final judge names must be unique.');
   if(!isset($clean[$chiefKey]))throw new RuntimeException('Select one Final Chief Judge.');

   $existingStmt=$pdo->prepare("SELECT id FROM bdc_scoring_judges WHERE round_id=:r");
   $existingStmt->execute(['r'=>$roundId]);
   $existingIds=array_map('intval',$existingStmt->fetchAll(PDO::FETCH_COLUMN));

   $pdo->beginTransaction();
   try{
    $keptIds=[];
    $chiefId=0;
    $order=1;

    $update=$pdo->prepare("UPDATE bdc_scoring_judges SET judge_name=:name,judge_order=:ord,is_chief=:chief,scoring_scope='all' WHERE id=:id AND round_id=:r");
    $insert=$pdo->prepare("INSERT INTO bdc_scoring_judges(round_id,judge_name,judge_order,is_chief,scoring_scope) VALUES(:r,:name,:ord,:chief,'all')");

    foreach($clean as $key=>$row){
     $isChief=$key===$chiefKey?1:0;
     if($row['id']>0 && in_array($row['id'],$existingIds,true)){
      $update->execute(['name'=>$row['name'],'ord'=>$order,'chief'=>$isChief,'id'=>$row['id'],'r'=>$roundId]);
      $judgeId=$row['id'];
     }else{
      $insert->execute(['r'=>$roundId,'name'=>$row['name'],'ord'=>$order,'chief'=>$isChief]);
      $judgeId=(int)$pdo->lastInsertId();
     }
     $keptIds[]=$judgeId;
     if($isChief)$chiefId=$judgeId;
     $order++;
    }

    $removeIds=array_values(array_diff($existingIds,$keptIds));
    if($removeIds){
     $placeholders=implode(',',array_fill(0,count($removeIds),'?'));
     $pdo->prepare("DELETE FROM bdc_scoring_final_marks WHERE round_id=? AND judge_id IN ($placeholders)")
         ->execute(array_merge([$roundId],$removeIds));
     $pdo->prepare("DELETE FROM bdc_scoring_judges WHERE round_id=? AND id IN ($placeholders)")
         ->execute(array_merge([$roundId],$removeIds));
     $pdo->prepare("DELETE FROM bdc_scoring_final_results WHERE round_id=:r")->execute(['r'=>$roundId]);
    }

    $pdo->prepare("UPDATE bdc_scoring_rounds SET chief_judge_id=:chief WHERE id=:r")
        ->execute(['chief'=>$chiefId,'r'=>$roundId]);

    auditScoring($pdo,$roundId,$userId,'final_judges_saved',[
      'count'=>count($clean),
      'chief_judge_id'=>$chiefId,
      'removed_judge_ids'=>$removeIds
    ]);
    $pdo->commit();
    $notice='Final judges saved. Existing judges were updated and new judges were appended.';
   }catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    throw $e;
   }

  }elseif($action==='save_final_scores' || $action==='submit_final_scores'){
   @set_time_limit(180);
   $postedFinalRanks=$_POST['final_rank']??[];
   if(!empty($_POST['final_rank_payload'])){
    $decodedFinalRanks=json_decode((string)$_POST['final_rank_payload'],true);
    if(!is_array($decodedFinalRanks))throw new RuntimeException('The Final ranking payload could not be read.');
    $postedFinalRanks=$decodedFinalRanks;
   }
   $roundId=(int)($_POST['round_id']??0);
   $roundForFinal=loadRound($pdo,$roundId);
   if(!$roundForFinal||$roundForFinal['round_type']!=='final')throw new RuntimeException('Final round not found.');

   $finalWitnesses=[
    trim((string)($_POST['witness_1']??'')),
    trim((string)($_POST['witness_2']??'')),
    trim((string)($_POST['witness_3']??''))
   ];
   $finalAdministrator=trim((string)($_POST['scoring_administrator']??''));
   $pdo->prepare("UPDATE bdc_scoring_rounds SET witness_1=NULLIF(:w1,''),witness_2=NULLIF(:w2,''),witness_3=NULLIF(:w3,''),scoring_administrator=NULLIF(:admin,'') WHERE id=:r")
       ->execute(['w1'=>$finalWitnesses[0],'w2'=>$finalWitnesses[1],'w3'=>$finalWitnesses[2],'admin'=>$finalAdministrator,'r'=>$roundId]);

   $pairStmt=$pdo->prepare("SELECT id FROM bdc_scoring_final_pairs WHERE round_id=:r AND pairing_status='confirmed' ORDER BY pair_number");
   $pairStmt->execute(['r'=>$roundId]);
   $pairIds=array_map('intval',$pairStmt->fetchAll(PDO::FETCH_COLUMN));
   if(!$pairIds)throw new RuntimeException('Confirm Final pairing before entering rankings.');

   $judgeStmt=$pdo->prepare("SELECT id FROM bdc_scoring_judges WHERE round_id=:r ORDER BY judge_order");
   $judgeStmt->execute(['r'=>$roundId]);
   $judgeIds=array_map('intval',$judgeStmt->fetchAll(PDO::FETCH_COLUMN));

   $upsert=$pdo->prepare("
    INSERT INTO bdc_scoring_final_marks(round_id,pair_id,judge_id,rank_value,updated_by)
    VALUES(:r,:p,:j,:rank,:u)
    ON DUPLICATE KEY UPDATE rank_value=VALUES(rank_value),updated_by=VALUES(updated_by),updated_at=NOW()
   ");

   foreach($postedFinalRanks as $pairId=>$judgeRanks){
    $pairId=(int)$pairId;
    if(!in_array($pairId,$pairIds,true))continue;
    foreach($judgeRanks as $judgeId=>$rankValue){
     $judgeId=(int)$judgeId;
     if(!in_array($judgeId,$judgeIds,true))continue;
     $rankValue=(int)$rankValue;
     if($rankValue<1||$rankValue>count($pairIds))throw new RuntimeException('Final ranks must be between 1 and '.count($pairIds).'.');
     $upsert->execute(['r'=>$roundId,'p'=>$pairId,'j'=>$judgeId,'rank'=>$rankValue,'u'=>$userId?:null]);
    }
   }

   if($action==='submit_final_scores'){
    calculateRelativePlacement($pdo,$roundId,$userId);
    $pdo->prepare("UPDATE bdc_scoring_rounds SET status='scores_submitted' WHERE id=:r")->execute(['r'=>$roundId]);
    $notice='Final scores submitted and Relative Placement ranking calculated.';
   }else{
    auditScoring($pdo,$roundId,$userId,'final_scores_saved');
    $notice='Final ranking draft saved.';
   }

  }elseif($action==='calculate_final_ranking'){
   @set_time_limit(180);
   $postedFinalRanks=$_POST['final_rank']??[];
   if(!empty($_POST['final_rank_payload'])){
    $decodedFinalRanks=json_decode((string)$_POST['final_rank_payload'],true);
    if(!is_array($decodedFinalRanks))throw new RuntimeException('The Final ranking payload could not be read.');
    $postedFinalRanks=$decodedFinalRanks;
   }
   $roundId=(int)($_POST['round_id']??0);
   $roundForFinal=loadRound($pdo,$roundId);
   if(!$roundForFinal||$roundForFinal['round_type']!=='final')throw new RuntimeException('Final round not found.');

   $pairStmt=$pdo->prepare("SELECT id FROM bdc_scoring_final_pairs WHERE round_id=:r AND pairing_status='confirmed' ORDER BY pair_number");
   $pairStmt->execute(['r'=>$roundId]);
   $pairIds=array_map('intval',$pairStmt->fetchAll(PDO::FETCH_COLUMN));
   if(!$pairIds)throw new RuntimeException('Confirm Final pairing before calculating rankings.');

   $judgeStmt=$pdo->prepare("SELECT id FROM bdc_scoring_judges WHERE round_id=:r ORDER BY judge_order");
   $judgeStmt->execute(['r'=>$roundId]);
   $judgeIds=array_map('intval',$judgeStmt->fetchAll(PDO::FETCH_COLUMN));

   $upsert=$pdo->prepare("
    INSERT INTO bdc_scoring_final_marks(round_id,pair_id,judge_id,rank_value,updated_by)
    VALUES(:r,:p,:j,:rank,:u)
    ON DUPLICATE KEY UPDATE rank_value=VALUES(rank_value),updated_by=VALUES(updated_by),updated_at=NOW()
   ");

   foreach($postedFinalRanks as $pairId=>$judgeRanks){
    $pairId=(int)$pairId;
    if(!in_array($pairId,$pairIds,true))continue;
    foreach($judgeRanks as $judgeId=>$rankValue){
     $judgeId=(int)$judgeId;
     if(!in_array($judgeId,$judgeIds,true))continue;
     $rankValue=(int)$rankValue;
     if($rankValue<1||$rankValue>count($pairIds)){
      throw new RuntimeException('Final ranks must be between 1 and '.count($pairIds).'.');
     }
     $upsert->execute([
      'r'=>$roundId,'p'=>$pairId,'j'=>$judgeId,
      'rank'=>$rankValue,'u'=>$userId?:null
     ]);
    }
   }

   calculateRelativePlacement($pdo,$roundId,$userId);
   $notice='Current scores saved. Relative Placement calculated and table sorted by Final Rank.';

  }elseif($action==='save_final_pairing'){
   $roundId=(int)($_POST['round_id']??0);
   $roundForPairing=loadRound($pdo,$roundId);
   if(!$roundForPairing||$roundForPairing['round_type']!=='final')throw new RuntimeException('Final round not found.');
   $pairs=$_POST['pair']??[];
   $pdo->beginTransaction();
   try{
    $pdo->prepare("DELETE FROM bdc_scoring_final_pairs WHERE round_id=:r")->execute(['r'=>$roundId]);
    $insertPair=$pdo->prepare("INSERT INTO bdc_scoring_final_pairs(round_id,pair_number,leader_entry_id,follower_entry_id,pairing_status,created_by)
      VALUES(:r,:n,:l,NULLIF(:f,0),'draft',:u)");
    $number=1;$usedFollowers=[];
    foreach($pairs as $leaderId=>$followerId){
      $leaderId=(int)$leaderId;$followerId=(int)$followerId;
      if($leaderId<1)continue;
      if($followerId>0){
       if(isset($usedFollowers[$followerId]))throw new RuntimeException('A Follower can only be paired once.');
       $usedFollowers[$followerId]=true;
      }
      $insertPair->execute(['r'=>$roundId,'n'=>$number++,'l'=>$leaderId,'f'=>$followerId,'u'=>$userId?:null]);
    }
    auditScoring($pdo,$roundId,$userId,'final_pairing_saved',['pairs'=>$number-1]);
    $pdo->commit();
    $notice='Final pairing draft saved.';
   }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
  }elseif($action==='random_final_pairing'){
   $roundId=(int)($_POST['round_id']??0);
   $leaders=$pdo->prepare("SELECT id FROM bdc_scoring_entries WHERE round_id=:r AND dance_role='leader' AND entry_status='active' ORDER BY bib_number");
   $followers=$pdo->prepare("SELECT id FROM bdc_scoring_entries WHERE round_id=:r AND dance_role='follower' AND entry_status='active' ORDER BY bib_number");
   $leaders->execute(['r'=>$roundId]);$followers->execute(['r'=>$roundId]);
   $leaderIds=array_map('intval',$leaders->fetchAll(PDO::FETCH_COLUMN));
   $followerIds=array_map('intval',$followers->fetchAll(PDO::FETCH_COLUMN));
   shuffle($followerIds);
   $pdo->beginTransaction();
   try{
    $pdo->prepare("DELETE FROM bdc_scoring_final_pairs WHERE round_id=:r")->execute(['r'=>$roundId]);
    $ins=$pdo->prepare("INSERT INTO bdc_scoring_final_pairs(round_id,pair_number,leader_entry_id,follower_entry_id,pairing_status,created_by)
      VALUES(:r,:n,:l,NULLIF(:f,0),'draft',:u)");
    foreach($leaderIds as $i=>$leaderId)$ins->execute(['r'=>$roundId,'n'=>$i+1,'l'=>$leaderId,'f'=>$followerIds[$i]??0,'u'=>$userId?:null]);
    auditScoring($pdo,$roundId,$userId,'final_pairing_randomized');
    $pdo->commit();
    $notice='Random Final pairing generated. Review before confirming.';
   }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
  }elseif($action==='confirm_final_pairing'){
   $roundId=(int)($_POST['round_id']??0);
   $missing=$pdo->prepare("SELECT COUNT(*) FROM bdc_scoring_final_pairs WHERE round_id=:r AND follower_entry_id IS NULL");
   $missing->execute(['r'=>$roundId]);
   if((int)$missing->fetchColumn()>0)throw new RuntimeException('Every Final Leader must have a Follower before confirming.');
   $pdo->prepare("UPDATE bdc_scoring_final_pairs SET pairing_status='confirmed' WHERE round_id=:r")->execute(['r'=>$roundId]);
   auditScoring($pdo,$roundId,$userId,'final_pairing_confirmed');
   $pdo->prepare("DELETE FROM bdc_scoring_final_results WHERE round_id=:r")->execute(['r'=>$roundId]);$notice='Final pairing confirmed. Relative Placement scoring is now available below.';
  }elseif($action==='generate_results'){$roundId=(int)$_POST['round_id'];$round=loadRound($pdo,$roundId);if(!$round)throw new RuntimeException('Round not found.');computeResults($pdo,$round,$userId);$notice='Results generated. Review before publishing or discarding.';
  }elseif($action==='discard_results'){$roundId=(int)$_POST['round_id'];$pdo->prepare("UPDATE bdc_scoring_rounds SET status='discarded' WHERE id=:r")->execute(['r'=>$roundId]);auditScoring($pdo,$roundId,$userId,'draft_result_discarded');$notice='Generated result discarded. Scores and registration were preserved.';
  }elseif($action==='publish_results'){
   $roundId=(int)$_POST['round_id'];$round=loadRound($pdo,$roundId);if(!$round||!in_array($round['status'],['awaiting_decision','republish_required','discarded'],true))throw new RuntimeException('Generate results before publishing.');$html=buildResultHtml($pdo,$round);$name='HEATS-'.safeFile($round['event_name']).'-'.safeFile($round['division']).'-v'.((int)$round['generated_version']).'.html';file_put_contents(resultRoot().'/'.$name,$html);$relative='public/results/'.$name;$public=url($relative);
   $old=$pdo->prepare("SELECT id FROM bdc_result_documents WHERE event_id=:e AND document_category='heats' AND status='published' ORDER BY id DESC LIMIT 1");$old->execute(['e'=>$round['event_id']]);$docId=(int)$old->fetchColumn();$title='HEATS — '.$round['event_name'].' ('.ucfirst($round['division']).')';if($docId){$pdo->prepare("UPDATE bdc_result_documents SET title=:t,file_type='external',url=:u,storage_path=:s,source='scoring_engine',version_number=version_number+1,updated_at=NOW() WHERE id=:id")->execute(['t'=>$title,'u'=>$public,'s'=>$relative,'id'=>$docId]);}else{$pdo->prepare("INSERT INTO bdc_result_documents(event_id,title,document_category,file_type,url,storage_path,status,source,created_by) VALUES(:e,:t,'heats','external',:u,:s,'published','scoring_engine',:uid)")->execute(['e'=>$round['event_id'],'t'=>$title,'u'=>$public,'s'=>$relative,'uid'=>$userId]);$docId=(int)$pdo->lastInsertId();}$pdo->prepare("UPDATE bdc_scoring_rounds SET status='published',published_document_id=:d WHERE id=:r")->execute(['d'=>$docId,'r'=>$roundId]);auditScoring($pdo,$roundId,$userId,'published',['document_id'=>$docId,'file'=>$relative]);$notice='Heats result published to the Result Repository. Scores remain saved.';
  }
 }
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$error=$e->getMessage();}

$events=$pdo->query("SELECT id,name,event_date FROM bdc_events ORDER BY event_date DESC,name")->fetchAll();
$competitorSuggestions=$pdo->query("SELECT c.id,c.bdc_id,c.exact_name,c.dance_role,c.current_division,c.status,
 COALESCE(SUM(CASE WHEN p.division='novice' AND p.dance_role IN(c.dance_role,'both') THEN p.points ELSE 0 END),0) novice_points,
 COALESCE(SUM(CASE WHEN p.division='intermediate' AND p.dance_role IN(c.dance_role,'both') THEN p.points ELSE 0 END),0) intermediate_points,
 COALESCE(SUM(CASE WHEN p.division='advanced' AND p.dance_role IN(c.dance_role,'both') THEN p.points ELSE 0 END),0) advanced_points,
 GREATEST(MAX(CASE WHEN p.division='intermediate' AND p.dance_role IN(c.dance_role,'both') THEN 1 ELSE 0 END),EXISTS(SELECT 1 FROM bdc_participant_results pr WHERE pr.competitor_id=c.id AND pr.division='intermediate' AND pr.dance_role IN(c.dance_role,'both'))) competed_intermediate,
 GREATEST(MAX(CASE WHEN p.division='advanced' AND p.dance_role IN(c.dance_role,'both') THEN 1 ELSE 0 END),EXISTS(SELECT 1 FROM bdc_participant_results pr WHERE pr.competitor_id=c.id AND pr.division='advanced' AND pr.dance_role IN(c.dance_role,'both'))) competed_advanced,
 GREATEST(MAX(CASE WHEN p.division='all_star' AND p.dance_role IN(c.dance_role,'both') THEN 1 ELSE 0 END),EXISTS(SELECT 1 FROM bdc_participant_results pr WHERE pr.competitor_id=c.id AND pr.division='all_star' AND pr.dance_role IN(c.dance_role,'both'))) competed_all_star
 FROM bdc_competitors c
 LEFT JOIN bdc_point_transactions p ON p.competitor_id=c.id
 WHERE c.status<>'archived'
 GROUP BY c.id,c.bdc_id,c.exact_name,c.dance_role,c.current_division,c.status
 ORDER BY c.exact_name LIMIT 1500")->fetchAll();
$round=$roundId?loadRound($pdo,$roundId):null;
$registrationDeskLink=null;$registrationDeskUrl='';
if($round){
 $stmt=$pdo->prepare("SELECT * FROM bdc_registration_desk_links WHERE event_id=:event AND division=:division LIMIT 1");
 $stmt->execute(['event'=>$round['event_id'],'division'=>$round['division']]);
 $registrationDeskLink=$stmt->fetch();
 if($registrationDeskLink)$registrationDeskUrl=registrationDeskUrl($registrationDeskLink,$roundId);
}

if($round){
 $orphanTieStmt=$pdo->prepare("
  SELECT sr.entry_id,sr.rank_number,se.dance_role
  FROM bdc_scoring_results sr
  JOIN bdc_scoring_entries se ON se.id=sr.entry_id
  LEFT JOIN (
   SELECT se2.dance_role,sr2.rank_number,sr2.total_score,sr2.chief_score,COUNT(*) AS tied_count
   FROM bdc_scoring_results sr2
   JOIN bdc_scoring_entries se2 ON se2.id=sr2.entry_id
   WHERE sr2.round_id=:round_id_1 AND sr2.result_status='tie_pending'
   GROUP BY se2.dance_role,sr2.rank_number,sr2.total_score,sr2.chief_score
  ) g ON g.dance_role=se.dance_role
      AND g.rank_number=sr.rank_number
      AND ABS(g.total_score-sr.total_score)<0.0001
      AND ABS(g.chief_score-sr.chief_score)<0.0001
  WHERE sr.round_id=:round_id_2
    AND sr.result_status='tie_pending'
    AND COALESCE(g.tied_count,0)<2
 ");
 $orphanTieStmt->execute(['round_id_1'=>$roundId,'round_id_2'=>$roundId]);
 $orphanTies=$orphanTieStmt->fetchAll();

 if($orphanTies){
  $fixTie=$pdo->prepare("
   UPDATE bdc_scoring_results
   SET result_status=:status,
       alternate_rank=:alternate_rank,
       updated_at=NOW()
   WHERE round_id=:round_id AND entry_id=:entry_id
  ");
  foreach($orphanTies as $orphan){
   $rank=(int)$orphan['rank_number'];
   if($rank<=(int)$round['callback_count']){
    $status='callback';$alternateRank=null;
   }elseif($rank<=(int)$round['callback_count']+3){
    $status='alternate';$alternateRank=$rank-(int)$round['callback_count'];
   }else{
    $status='eliminated';$alternateRank=null;
   }
   $fixTie->execute([
    'status'=>$status,
    'alternate_rank'=>$alternateRank,
    'round_id'=>$roundId,
    'entry_id'=>(int)$orphan['entry_id']
   ]);
  }
 }
}
$rounds=$pdo->query("
 SELECT r.*,e.name event_name,e.event_date,
        EXISTS(SELECT 1 FROM bdc_scoring_rounds child WHERE child.parent_round_id=r.id) AS has_child_round
 FROM bdc_scoring_rounds r
 JOIN bdc_events e ON e.id=r.event_id
 ORDER BY r.updated_at DESC
 LIMIT 30
")->fetchAll();
$judges=[];$entries=['leader'=>[],'follower'=>[]];$marks=[];$results=[];$finalPairs=[];$finalMarks=[];$finalResults=[];
if($round){$s=$pdo->prepare('SELECT * FROM bdc_scoring_judges WHERE round_id=:r ORDER BY judge_order');$s->execute(['r'=>$roundId]);$judges=$s->fetchAll();$s=$pdo->prepare("SELECT se.*,c.bdc_id,c.status AS competitor_status FROM bdc_scoring_entries se JOIN bdc_competitors c ON c.id=se.competitor_id WHERE se.round_id=:r AND se.entry_status='active' ORDER BY se.dance_role,se.bib_number");$s->execute(['r'=>$roundId]);foreach($s->fetchAll() as $x)$entries[$x['dance_role']][]=$x;$s=$pdo->prepare('SELECT * FROM bdc_scoring_marks WHERE round_id=:r');$s->execute(['r'=>$roundId]);foreach($s->fetchAll() as $m)$marks[$m['entry_id']][$m['judge_id']]=$m;$s=$pdo->prepare('SELECT * FROM bdc_scoring_results WHERE round_id=:r');$s->execute(['r'=>$roundId]);foreach($s->fetchAll() as $r)$results[$r['entry_id']]=$r;
if($results){
 foreach(['leader','follower'] as $sortRole){
  usort($entries[$sortRole],function($a,$b)use($results){
   $ra=$results[$a['id']]??null;$rb=$results[$b['id']]??null;
   $rankA=$ra?(int)$ra['rank_number']:PHP_INT_MAX;
   $rankB=$rb?(int)$rb['rank_number']:PHP_INT_MAX;
   if($rankA!==$rankB)return $rankA<=>$rankB;
   $totalA=$ra?(float)$ra['total_score']:-1;
   $totalB=$rb?(float)$rb['total_score']:-1;
   if($totalA!==$totalB)return $totalB<=>$totalA;
   return (int)$a['bib_number']<=>(int)$b['bib_number'];
  });
 }
}
$s=$pdo->prepare("SELECT fp.*,le.display_name leader_name,le.bib_number leader_bib,fe.display_name follower_name,fe.bib_number follower_bib FROM bdc_scoring_final_pairs fp JOIN bdc_scoring_entries le ON le.id=fp.leader_entry_id LEFT JOIN bdc_scoring_entries fe ON fe.id=fp.follower_entry_id WHERE fp.round_id=:r ORDER BY fp.pair_number");$s->execute(['r'=>$roundId]);$finalPairs=$s->fetchAll();
$s=$pdo->prepare("SELECT pair_id,judge_id,rank_value FROM bdc_scoring_final_marks WHERE round_id=:r");$s->execute(['r'=>$roundId]);foreach($s->fetchAll() as $fm)$finalMarks[(int)$fm['pair_id']][(int)$fm['judge_id']]=(int)$fm['rank_value'];
$s=$pdo->prepare("SELECT * FROM bdc_scoring_final_results WHERE round_id=:r ORDER BY final_rank");$s->execute(['r'=>$roundId]);foreach($s->fetchAll() as $fr)$finalResults[(int)$fr['pair_id']]=$fr;
if($finalResults){
 usort($finalPairs,function($a,$b)use($finalResults){
  $rankA=(int)($finalResults[(int)$a['id']]['final_rank']??PHP_INT_MAX);
  $rankB=(int)($finalResults[(int)$b['id']]['final_rank']??PHP_INT_MAX);
  if($rankA!==$rankB)return $rankA<=>$rankB;
  return (int)$a['pair_number']<=>(int)$b['pair_number'];
 });
}}
$tieGroups=[];
if($round){
 $tieStmt=$pdo->prepare("
  SELECT sr.entry_id,sr.total_score,sr.chief_score,sr.rank_number,
         se.dance_role,se.display_name,se.bib_number
  FROM bdc_scoring_results sr
  JOIN bdc_scoring_entries se ON se.id=sr.entry_id
  JOIN (
   SELECT se2.dance_role,sr2.rank_number,sr2.total_score,sr2.chief_score
   FROM bdc_scoring_results sr2
   JOIN bdc_scoring_entries se2 ON se2.id=sr2.entry_id
   WHERE sr2.round_id=:round_id_1 AND sr2.result_status='tie_pending'
   GROUP BY se2.dance_role,sr2.rank_number,sr2.total_score,sr2.chief_score
   HAVING COUNT(*)>1
  ) valid_tie
    ON valid_tie.dance_role=se.dance_role
   AND valid_tie.rank_number=sr.rank_number
   AND ABS(valid_tie.total_score-sr.total_score)<0.0001
   AND ABS(valid_tie.chief_score-sr.chief_score)<0.0001
  WHERE sr.round_id=:round_id_2
    AND sr.result_status='tie_pending'
  ORDER BY se.dance_role,sr.rank_number,se.bib_number
 ");
 $tieStmt->execute(['round_id_1'=>$roundId,'round_id_2'=>$roundId]);
 foreach($tieStmt->fetchAll() as $tieRow){
  $key=$tieRow['dance_role'].'|'.$tieRow['rank_number'].'|'.$tieRow['total_score'].'|'.$tieRow['chief_score'];
  if(!isset($tieGroups[$key])){
   $tieGroups[$key]=[
    'role'=>$tieRow['dance_role'],
    'rank'=>(int)$tieRow['rank_number'],
    'total'=>(float)$tieRow['total_score'],
    'chief'=>(float)$tieRow['chief_score'],
    'competitors'=>[]
   ];
  }
  $tieGroups[$key]['competitors'][]=$tieRow;
 }
}

$nextBib=['leader'=>1,'follower'=>1];
foreach(['leader','follower'] as $role){
 $activeBibs=array_map(static fn($row)=>(int)$row['bib_number'],$entries[$role]);
 $nextBib[$role]=$activeBibs?max($activeBibs)+1:1;
}
$csrf=Csrf::token();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Scoring Dashboard | BDC Admin</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>.score-input{width:48px;text-align:center}.sticky-actions{position:sticky;bottom:0;background:#fff;border-top:1px solid #ddd;padding:10px;z-index:5}.role-card{min-height:220px}.status-pill{text-transform:capitalize}.score-table th{white-space:nowrap;font-size:.8rem}.score-table td{vertical-align:middle}.callback{background:#d1e7dd!important}.alternate{background:#fff3cd!important}.tie_pending{background:#f8d7da!important}</style></head><body class="bg-light"><nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="../">BDC Admin</a><div class="d-flex gap-2"><a class="btn btn-warning btn-sm" href="https://bachatadancecouncil.com/">BDC Home</a><a class="btn btn-outline-light btn-sm" href="../">Dashboard</a></div></div></nav><div class="container-fluid py-4" style="max-width:1600px"><div class="d-flex justify-content-between align-items-start mb-3"><div><h1 class="h3 mb-1">Scoring Dashboard</h1><div class="text-muted">Manual Scoring Engine · Event Round Workflow</div></div>
<?php if($round):?>
<div class="card shadow-sm mb-4 border-primary" id="registration-desk-sync">
 <div class="card-header d-flex justify-content-between align-items-center">
  <strong>Registration Desk</strong>
  <span class="badge text-bg-primary">LIVE SYNC</span>
 </div>
 <div class="card-body">
  <?php if($registrationDeskUrl):?>
   <div class="input-group mb-3">
    <input class="form-control" id="registrationDeskUrl" value="<?=e($registrationDeskUrl)?>" readonly>
    <button type="button" class="btn btn-outline-primary" onclick="navigator.clipboard.writeText(document.getElementById('registrationDeskUrl').value)">Copy Link</button>
    <a class="btn btn-primary" href="<?=e($registrationDeskUrl)?>" target="_blank">Open Desk</a>
   </div>
  <?php else:?>
   <div class="alert alert-warning mb-3">The secure token is not visible in this session. Regenerate the desk link to create a new shareable URL.</div>
  <?php endif;?>
  <div class="row g-3" id="deskSyncStats">
   <div class="col-md-3"><div class="border rounded p-3"><strong>Leaders</strong><div class="fs-4" data-stat="leaders">—</div></div></div>
   <div class="col-md-3"><div class="border rounded p-3"><strong>Followers</strong><div class="fs-4" data-stat="followers">—</div></div></div>
   <div class="col-md-3"><div class="border rounded p-3"><strong>Missing Bibs</strong><div class="fs-4" data-stat="missing">—</div></div></div>
   <div class="col-md-3"><div class="border rounded p-3"><strong>Last Update</strong><div class="fs-6" data-stat="updated">—</div></div></div>
  </div>
 </div>
</div>
<?php endif;?>
<?php if($round):?><span class="badge text-bg-primary status-pill"><?=e(str_replace('_',' ',$round['status']))?></span><?php endif;?></div><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><?php if($notice):?><div class="alert alert-success"><?=e($notice)?></div><?php endif;?>
<?php if(!$round):?>
<div class="card shadow-sm mb-4"><div class="card-body">
<h2 class="h5">Create Scoring Round</h2>
<p class="text-muted">Select an existing event or enter a new event name. New event details can be completed later under Events &amp; Tickets.</p>
<form method="post" class="row g-3">
<input type="hidden" name="_csrf" value="<?=e($csrf)?>">
<input type="hidden" name="action" value="create_round">
<div class="col-lg-7">
<label class="form-label">Select Existing Event</label>
<select class="form-select" name="event_id">
<option value="">Select event</option>
<?php foreach($events as $e):?><option value="<?=$e['id']?>"><?=e(($e['event_date']?:'Date pending').' — '.$e['name'])?></option><?php endforeach;?>
</select>
</div>
<div class="col-lg-5">
<label class="form-label">Division</label>
<select class="form-select" name="division">
<option value="novice">Novice</option>
<option value="intermediate">Intermediate</option>
<option value="advanced">Advanced</option>
<option value="all_star">All Star</option>
</select>
</div>
<div class="col-12"><div class="text-center text-muted fw-semibold">OR CREATE A BASIC EVENT</div></div>
<div class="col-lg-8">
<label class="form-label">New Event Name</label>
<input class="form-control" name="new_event_name" maxlength="190" placeholder="Example: BASS 8 August 2026 at Caliente">
</div>
<div class="col-lg-4">
<label class="form-label">Event Date</label>
<input class="form-control" type="date" name="new_event_date">
<div class="form-text">Optional now. Complete or correct it later in Events &amp; Tickets.</div>
</div>
<div class="col-12">
<h3 class="h6 mb-2">Competition Format</h3>
<p class="text-muted small mb-2">Choose the first round for this event and division.</p>
<div class="d-flex flex-wrap gap-2">
<button class="btn btn-success" name="round_type" value="heats">Start with Heats</button>
<button class="btn btn-dark" name="round_type" value="final">Go Straight to Final</button>
</div>
</div>
<div class="col-12"><small class="text-muted">Starting with Heats keeps the existing options to continue to either Semifinal or Final. Go Straight to Final when no qualification round is required.</small></div>
</form>
</div></div>
<div class="card shadow-sm"><div class="card-body">
<h2 class="h5">Saved Rounds</h2>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Event</th><th>Division</th><th>Round</th><th>Status</th><th>Updated</th><th class="text-end">Actions</th></tr></thead>
<tbody><?php foreach($rounds as $r):
 $status=(string)$r['status'];
 $label=match($status){
  'draft'=>'Draft',
  'awaiting_decision'=>'Awaiting Next Round Decision',
  'scores_submitted'=>'Scores Submitted',
  'pending_approval'=>'Pending Super Admin Approval',
  'completed'=>'Completed',
  'archived'=>'Archived',
  default=>ucwords(str_replace('_',' ',$status))
 };
 if((int)$r['has_child_round']===1 && $status==='awaiting_decision')$label='Completed';
 $badge=match($label){
  'Completed'=>'text-bg-success',
  'Archived'=>'text-bg-dark',
  'Pending Super Admin Approval'=>'text-bg-warning',
  'Scores Submitted'=>'text-bg-primary',
  'Awaiting Next Round Decision'=>'text-bg-warning',
  default=>'text-bg-secondary'
 };
?>
<tr>
<td><?=e($r['event_name'])?></td>
<td><?=e(ucfirst($r['division']))?></td>
<td><?=e(ucfirst($r['round_type']))?></td>
<td><span class="badge <?=$badge?>"><?=e($label)?></span></td>
<td><?=e($r['updated_at'])?></td>
<td class="text-end">
 <div class="d-inline-flex gap-2">
  <a class="btn btn-sm btn-outline-dark" href="?round_id=<?=$r['id']?>">Open</a>
  <?php if($r['status']!=='archived' && empty($r['locked_at'])):?>
  <form method="post" onsubmit="return confirmDeleteWorkflow(this,'<?=e(addslashes($r['event_name']))?>','<?=e(ucfirst($r['division']))?>');">
   <input type="hidden" name="_csrf" value="<?=e($csrf)?>">
   <input type="hidden" name="action" value="delete_scoring_workflow">
   <input type="hidden" name="event_id" value="<?=$r['event_id']?>">
   <input type="hidden" name="division" value="<?=e($r['division'])?>">
   <input type="hidden" name="delete_confirmation" value="">
   <button class="btn btn-sm btn-outline-danger">Delete All Test Scoring</button>
  </form>
  <?php endif;?>
 </div>
</td>
</tr>
<?php endforeach;?></tbody></table></div></div></div><?php else:?>
<div class="mb-3"><a href="?mode=manual" class="btn btn-outline-secondary btn-sm">← All rounds</a> <strong><?=e($round['event_name'])?></strong> · <?=e(ucfirst($round['division']))?> · <?=e(ucfirst($round['round_type']))?></div>
<?php if($round['round_type']==='final'):?>
<?php $finalDivisionSuggestions=array_values(array_filter($competitorSuggestions,function($suggestion)use($round){
 $check=DivisionProgressionService::eligibilityFor((string)$round['division'],(float)$suggestion['novice_points'],(float)$suggestion['intermediate_points'],(float)$suggestion['advanced_points'],(string)$suggestion['current_division'],!empty($suggestion['competed_intermediate']),!empty($suggestion['competed_advanced']),!empty($suggestion['competed_all_star']));
 return $check['eligible'];
}));?>
<datalist id="finalCompetitorSuggestions"><?php foreach($finalDivisionSuggestions as $suggestion):?><option value="<?=e($suggestion['bdc_id'])?>"><?=e($suggestion['exact_name'].' · '.ucfirst($suggestion['dance_role']).' · '.ucwords(str_replace('_',' ',$suggestion['current_division'])))?></option><?php endforeach;?></datalist>
<div class="card shadow-sm mb-4"><div class="card-body">
<div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
 <div>
  <h2 class="h5 mb-1">Final Dashboard</h2>
  <p class="text-muted mb-0">Match fixed couples first. Repository publication will appear only after Final scores are submitted and previewed.</p>
 </div>
 <?php if((int)$round['parent_round_id']>0):?>
 <form method="post" onsubmit="return confirm('Cancel this Final draft and return to the previous round? Final pairing data will be removed.');">
  <input type="hidden" name="_csrf" value="<?=e($csrf)?>">
  <input type="hidden" name="action" value="cancel_child_round">
  <input type="hidden" name="round_id" value="<?=$roundId?>">
  <button class="btn btn-outline-danger btn-sm">← Cancel Final &amp; Return</button>
 </form>
 <?php endif;?>
</div>
</div></div>

<div class="card shadow-sm mb-4"><div class="card-body">
 <h2 class="h5 mb-1">Add Competitors Directly to Final</h2>
 <p class="text-muted mb-3">Search suggestions show active BDC <?=e(ucwords(str_replace('_',' ',$round['division'])))?> competitors. Leaders and Followers are added independently, so the numbers may be different.</p>
 <div class="row g-3">
 <?php foreach(['leader'=>['Leaders','primary'],'follower'=>['Followers','danger']] as $finalRole=>$finalRoleMeta):?>
  <div class="col-lg-6"><div class="border rounded p-3 h-100">
   <h3 class="h6"><?=e($finalRoleMeta[0])?></h3>
   <form method="post" class="row g-2">
    <input type="hidden" name="_csrf" value="<?=e($csrf)?>">
    <input type="hidden" name="action" value="add_entry">
    <input type="hidden" name="round_id" value="<?=$roundId?>">
    <input type="hidden" name="dance_role" value="<?=$finalRole?>">
    <div class="col-3"><label class="form-label">Bib</label><input class="form-control" type="number" min="1" name="bib_number" value="<?=$nextBib[$finalRole]?>" required></div>
    <div class="col-9"><label class="form-label">BDC competitor</label><input class="form-control" name="competitor_search" list="finalCompetitorSuggestions" placeholder="Name or BDC ID" required><div class="form-text">Select a matching <?=e(ucwords(str_replace('_',' ',$round['division'])))?> competitor.</div></div>
    <div class="col-12"><button class="btn btn-<?=e($finalRoleMeta[1])?> w-100" name="entry_mode" value="existing">Add from BDC Database</button></div>
    <div class="col-12 mt-3"><div class="border border-warning rounded p-2 bg-warning-subtle">
     <div class="small fw-semibold mb-2">Missing from BDC only</div>
     <div class="form-check"><input class="form-check-input" type="checkbox" name="override_division" value="1" id="override_<?=$finalRole?>"><label class="form-check-label" for="override_<?=$finalRole?>">Confirm this dancer has no existing BDC record</label></div>
     <input class="form-control form-control-sm mt-2" name="override_reason" maxlength="255" placeholder="Required reason for override">
     <button class="btn btn-outline-dark btn-sm mt-2" name="entry_mode" value="create" onclick="return confirm('Create a provisional BDC record and add this missing competitor directly to the Final? Existing ineligible BDC competitors cannot use this option. The reason will be audited.')">Add Missing Non-BDC Competitor</button>
    </div></div>
   </form>
  </div></div>
 <?php endforeach;?>
 </div>
</div></div>

<div class="row g-3 mb-4">
 <div class="col-lg-6"><div class="card shadow-sm h-100">
  <div class="card-header fw-semibold bg-primary-subtle d-flex justify-content-between align-items-center">
   <span>Finalist Leaders</span>
   <form method="post">
    <input type="hidden" name="_csrf" value="<?=e($csrf)?>">
    <input type="hidden" name="action" value="add_next_finalist">
    <input type="hidden" name="round_id" value="<?=$roundId?>">
    <input type="hidden" name="dance_role" value="leader">
    <button class="btn btn-sm btn-primary">Add Next Ranked Leader</button>
   </form>
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
   <form method="post">
    <input type="hidden" name="_csrf" value="<?=e($csrf)?>">
    <input type="hidden" name="action" value="add_next_finalist">
    <input type="hidden" name="round_id" value="<?=$roundId?>">
    <input type="hidden" name="dance_role" value="follower">
    <button class="btn btn-sm btn-danger">Add Next Ranked Follower</button>
   </form>
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

<div class="card shadow-sm mb-4"><div class="card-body">
 <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div><h2 class="h5 mb-1">Match Competitors</h2><div class="text-muted small">Choose one Follower beside each Leader, or generate a random match.</div></div>
  <form method="post">
   <input type="hidden" name="_csrf" value="<?=e($csrf)?>">
   <input type="hidden" name="action" value="random_final_pairing">
   <input type="hidden" name="round_id" value="<?=$roundId?>">
   <button class="btn btn-warning">Random Match</button>
  </form>
 </div>

 <form method="post">
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
      <select class="form-select" name="pair[<?=$leader['id']?>]">
       <option value="0">Select Follower</option>
       <?php foreach($entries['follower'] as $follower):?>
        <option value="<?=$follower['id']?>" <?=$current && (int)$current['follower_entry_id']===(int)$follower['id']?'selected':''?>>
         Bib <?=$follower['bib_number']?> · <?=e($follower['display_name'])?>
        </option>
       <?php endforeach;?>
      </select>
     </td>
     <td><?=e($current['pairing_status']??'draft')?></td>
    </tr>
   <?php endforeach;?>
   </tbody>
  </table></div>
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
<div class="card shadow-sm mb-4"><div class="card-body">
 <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
  <div>
   <h2 class="h5 mb-1">Final Relative Placement Scoring</h2>
   <p class="text-muted mb-0">Each judge must rank every fixed couple once, from 1 to <?=count($finalPairs)?>. Duplicate ranks in one judge column are not allowed.</p>
  </div>
  <a class="btn btn-outline-primary" href="print.php?round_id=<?=$roundId?>" target="_blank">Print Final Judge Sheets</a>
 </div>

 <div class="card border-0 bg-light mb-3"><div class="card-body">
  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-2">
   <div>
    <h3 class="h6 mb-1">Final Judges</h3>
    <div class="text-muted small">The Final can use a different judging panel. Edit existing judges, append new judges, remove judges and select one Final Chief Judge.</div>
   </div>
  </div>
  <form method="post" id="finalJudgesForm">
   <input type="hidden" name="_csrf" value="<?=e($csrf)?>">
   <input type="hidden" name="action" value="save_final_judges">
   <input type="hidden" name="round_id" value="<?=$roundId?>">
   <div id="finalJudgesWrap">
   <?php
   $finalJudgeDisplay=$judges?:[
    ['id'=>0,'judge_name'=>'','is_chief'=>1],
    ['id'=>0,'judge_name'=>'','is_chief'=>0],
    ['id'=>0,'judge_name'=>'','is_chief'=>0]
   ];
   foreach($finalJudgeDisplay as $i=>$judge):
    $judgeKey='judge_'.$i;
   ?>
    <div class="input-group mb-2 judge-row" data-judge-row>
     <span class="input-group-text final-judge-number">Judge <?=$i+1?></span>
     <input type="hidden" name="final_judges[<?=$judgeKey?>][id]" value="<?=e((string)($judge['id']??0))?>">
     <input class="form-control" name="final_judges[<?=$judgeKey?>][name]" value="<?=e($judge['judge_name'])?>" placeholder="Final judge name" required>
     <span class="input-group-text"><input type="radio" name="final_chief_key" value="<?=$judgeKey?>" <?=(int)$judge['is_chief']?'checked':''?>> Chief</span>
     <button type="button" class="btn btn-outline-danger" onclick="removeFinalJudge(this)">Remove</button>
    </div>
   <?php endforeach;?>
   </div>
   <div class="d-flex gap-2 flex-wrap">
    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addFinalJudge()">+ Add Final Judge</button>
    <button class="btn btn-dark btn-sm">Save Final Judges</button>
   </div>
  </form>
 </div></div>

 <form method="post" id="finalScoreForm">
  <input type="hidden" name="_csrf" value="<?=e($csrf)?>">
  <input type="hidden" name="round_id" value="<?=$roundId?>">
  <input type="hidden" name="final_rank_payload" id="finalRankPayload" value="">
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
    <?php foreach($judges as $judgeIndex=>$judge):?><th class="final-judge-column" data-judge-page="<?=intdiv($judgeIndex,$finalJudgePageSize)?>" <?=$judgeIndex>=$finalJudgePageSize?'style="display:none"':''?>>J<?=$judgeIndex+1?><?=(int)$judge['is_chief']?' ★':''?></th><?php endforeach;?>
    <th>Relative Placement</th>
   </tr></thead>
   <tbody>
   <?php foreach($finalPairs as $pair):$finalResult=$finalResults[(int)$pair['id']]??null;?>
    <tr>
     <td class="fw-bold"><?= $finalResult ? (int)$finalResult['final_rank'] : '—' ?></td>
     <td>Couple <?=$pair['pair_number']?></td>
     <td><strong>Bib <?=$pair['leader_bib']?></strong><br><?=e($pair['leader_name'])?></td>
     <td><strong>Bib <?=$pair['follower_bib']?></strong><br><?=e($pair['follower_name'])?></td>
     <?php foreach($judges as $judgeIndex=>$judge):?>
      <td class="final-judge-column" data-judge-page="<?=intdiv($judgeIndex,$finalJudgePageSize)?>" <?=$judgeIndex>=$finalJudgePageSize?'style="display:none"':''?>><input class="form-control form-control-sm text-center final-rank-input" type="number" min="1" max="<?=count($finalPairs)?>" data-pair-id="<?=$pair['id']?>" data-judge-id="<?=$judge['id']?>" name="final_rank[<?=$pair['id']?>][<?=$judge['id']?>]" value="<?=e((string)($finalMarks[(int)$pair['id']][(int)$judge['id']]??''))?>" required></td>
     <?php endforeach;?>
     <td>
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
   <button class="btn btn-success" name="action" value="calculate_final_ranking">Calculate &amp; Sort Final Ranking</button>
   <button class="btn btn-primary" name="action" value="submit_final_scores">Submit Final Scores</button>
   <?php if($finalResults):?>
    <a class="btn btn-outline-primary" target="_blank" href="final-result.php?round_id=<?=$roundId?>">Print Final Scoring Sheet</a>
    <a class="btn btn-danger" href="publish.php?round_id=<?=$roundId?>">Review &amp; Publish Competition</a>
   <?php endif;?>
  </div>
 </form>
</div></div>
<?php else:?>
<div class="alert alert-secondary">
 <strong>Next step:</strong> confirm fixed couples to open manual Relative Placement scoring.
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
<datalist id="competitorSuggestions"><?php foreach($competitorSuggestions as $suggestion):?><option value="<?=e($suggestion['bdc_id'])?>"><?=e($suggestion['exact_name'].' · '.ucfirst($suggestion['dance_role']).($suggestion['status']==='pending'?' · Details pending':''))?></option><?php endforeach;?></datalist>
<div class="row g-3 mb-4">
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
</div></div></div>
<?php if($judges && ($entries['leader']||$entries['follower'])):$leaderPanelCount=count(array_filter($judges,fn($judge)=>in_array($judge['scoring_scope']??'all',['all','leader'],true)));$followerPanelCount=count(array_filter($judges,fn($judge)=>in_array($judge['scoring_scope']??'all',['all','follower'],true)));?><div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"><div><span class="badge text-bg-primary me-2">Leader Panel: <?=$leaderPanelCount?> judges</span><span class="badge text-bg-danger">Follower Panel: <?=$followerPanelCount?> judges</span></div><a class="btn btn-outline-dark btn-sm" href="#judge-setup">Reselect Judges</a></div><form method="post" id="heatsScoreForm" data-callback-count="<?=(int)$round['callback_count']?>"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="score_payload" id="scorePayload" value=""><div class="card shadow-sm mb-4"><div class="card-body"><h2 class="h5">Manual <?=e(ucfirst($round['round_type']))?> Score Entry</h2><div class="text-muted small mb-2">Enter 1 or YES for a YES mark. Enter A1, A2 or A3 for alternates. Every save retains the screen data.</div><div class="alert alert-info py-2 mb-3"><strong>Live Preview:</strong> totals and provisional ranks update immediately. Use Calculate &amp; Sort to review the server result before Submit Scores.</div><?php foreach(['leader'=>'Leaders','follower'=>'Followers'] as $role=>$label):?><h3 class="h6 mt-4"><?=$label?></h3><div class="table-responsive"><table class="table table-bordered score-table"><thead><tr><th>Bib</th><th>Name</th><?php foreach($judges as $j):?><th><?=e($j['judge_name'])?><?=(int)$j['is_chief']?' ★':''?></th><?php endforeach;?><th>Total</th><th>Result</th></tr></thead><tbody><?php foreach($entries[$role] as $x):$res=$results[$x['id']]??null;?><tr class="score-row <?=e($res['result_status']??'')?>" data-role="<?=$role?>" data-entry-id="<?=$x['id']?>"><td><?=$x['bib_number']?></td><td><?=e($x['display_name'])?></td><?php foreach($judges as $j):$assigned=in_array($j['scoring_scope']??'all',['all',$role],true);$m=$marks[$x['id']][$j['id']]??null;$val='';if($m){$val=$m['mark_type']==='yes'?'1':($m['mark_type']==='alt'?'A'.$m['alt_rank']:'');}?><td><?php if($assigned):?><input class="form-control form-control-sm score-input" data-entry-id="<?=$x['id']?>" data-judge-id="<?=$j['id']?>" data-chief="<?=(int)$j['is_chief']?>" name="mark[<?=$x['id']?>][<?=$j['id']?>]" value="<?=e($val)?>"><?php else:?><span class="badge text-bg-light border text-muted">Not Assigned</span><?php endif;?></td><?php endforeach;?><td><span class="live-total"><?=isset($res['total_score'])?number_format((float)$res['total_score'],1):'0.0'?></span><div class="small text-muted">YES <span class="live-yes">0</span> · Chief <span class="live-chief">0.0</span></div></td><td><span class="live-status"><?=e($res['result_status']??'Live preview')?></span> <span class="live-rank"><?=isset($res['rank_number'])?'#'.$res['rank_number']:''?></span></td></tr><?php endforeach;?></tbody></table></div><?php endforeach;?></div></div>
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
      <button class="btn btn-warning">Move Callbacks to Semifinal</button>
     </form>
     <form method="post">
      <input type="hidden" name="_csrf" value="<?=e($csrf)?>">
      <input type="hidden" name="action" value="create_next_round">
      <input type="hidden" name="round_id" value="<?=$roundId?>">
      <input type="hidden" name="next_round_type" value="final">
      <button class="btn btn-dark">Move Callbacks Directly to Final</button>
     </form>
    <?php elseif($round['round_type']==='semifinal'):?>
     <form method="post">
      <input type="hidden" name="_csrf" value="<?=e($csrf)?>">
      <input type="hidden" name="action" value="create_next_round">
      <input type="hidden" name="round_id" value="<?=$roundId?>">
      <input type="hidden" name="next_round_type" value="final">
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

<?php if($tieGroups):?>
<div class="card shadow-sm mt-3 mb-4 border-warning">
 <div class="card-header bg-warning-subtle fw-semibold">Chief Judge Tie Resolution</div>
 <div class="card-body">
  <p class="mb-3">These exact ties cross the callback cutoff. The Chief Judge must select the competitor who receives the callback place.</p>
  <?php foreach($tieGroups as $tieGroup):?>
   <div class="border rounded p-3 mb-3">
    <div class="fw-semibold mb-2">
     <?=e(ucfirst($tieGroup['role']))?> · Callback position #<?=$tieGroup['rank']?> ·
     Total <?=number_format($tieGroup['total'],1)?> · Chief Judge score <?=number_format($tieGroup['chief'],1)?>
    </div>
    <div class="d-flex flex-wrap gap-2">
     <?php foreach($tieGroup['competitors'] as $candidate):?>
      <form method="post" onsubmit="return confirm('Select <?=e(addslashes($candidate['display_name']))?> as the callback winner of this tie?');">
       <input type="hidden" name="_csrf" value="<?=e($csrf)?>">
       <input type="hidden" name="action" value="resolve_callback_tie">
       <input type="hidden" name="round_id" value="<?=$roundId?>">
       <input type="hidden" name="selected_entry_id" value="<?=$candidate['entry_id']?>">
       <button class="btn btn-warning">
        Select Bib <?=$candidate['bib_number']?> · <?=e($candidate['display_name'])?>
       </button>
      </form>
     <?php endforeach;?>
    </div>
   </div>
  <?php endforeach;?>
  <div class="small text-muted">The selected competitor becomes Callback. The remaining tied competitors become Alternates in order, then Eliminated if more than three remain.</div>
 </div>
</div>
<?php endif;?>

<?php endif;?>
<?php endif;?><?php endif;?></div><script>
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
  calculated.sort((a,b)=>b.total-a.total||b.chief-a.chief||b.yes-a.yes||a.entry-b.entry);
  calculated.forEach((item,index)=>{const rank=index+1;const status=rank<=callbackCount?'Callback':(rank<=callbackCount+3?'Alternate':'Eliminated');item.row.querySelector('.live-rank').textContent='#'+rank;item.row.querySelector('.live-status').textContent=status+' · Live';});
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
 scoreForm.addEventListener('submit',event=>{if(event.submitter&&['calculate_scores','submit_scores'].includes(event.submitter.value))showScoringProgress();
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
   const rank=parseInt(input.value||'0',10);
   if(!payload[pair])payload[pair]={};
   payload[pair][judge]=rank;
   if(!byJudge[judge])byJudge[judge]=[];
   byJudge[judge].push(rank);
  });

  const pairCount=finalScoreForm.querySelectorAll('tbody tr').length;
  Object.entries(byJudge).some(([judge,ranks])=>{
   if(ranks.length!==pairCount||ranks.some(rank=>rank<1||rank>pairCount)){
    invalidMessage='Every Final judge must rank every couple from 1 to '+pairCount+'.';
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
</script></body></html>
