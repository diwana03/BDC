<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\SchemaUpdater;
use App\Services\ResultStorageService;

Auth::requireAdmin();
$pdo=Database::connection();

function bdcV230IsSuperAdmin():bool{try{if(method_exists(\App\Core\Auth::class,'isSuperAdmin')&&\App\Core\Auth::isSuperAdmin())return true;}catch(\Throwable $e){}$u=\App\Core\Auth::user();$r=strtolower(str_replace(['-',' '],'_',trim((string)($u['role']??$u['user_role']??''))));return in_array($r,['super_admin','superadmin','owner','root','system_admin'],true)||!empty($u['is_super_admin']);}


$userId=(int)(Auth::user()['id']??0);
$isSuperAdmin=bdcV230IsSuperAdmin();
$roundId=(int)($_GET['round_id']??$_POST['round_id']??0);
$error='';
$notice='';

function loadApprovalRound(PDO $pdo,int $roundId):array{
 $stmt=$pdo->prepare("
  SELECT r.*,e.name AS event_name,e.event_date,e.venue,e.points_tier AS event_points_tier
  FROM bdc_test_scoring_rounds r
  JOIN bdc_test_events e ON e.id=r.event_id
  WHERE r.id=:id AND r.round_type='final'
 ");
 $stmt->execute(['id'=>$roundId]);
 $round=$stmt->fetch();
 if(!$round)throw new RuntimeException('Final round not found.');
 return $round;
}

function approvalOrdinal(int $number):string{
 if($number%100>=11&&$number%100<=13)return $number.'th';
 return $number.([1=>'st',2=>'nd',3=>'rd'][$number%10]??'th');
}

function approvalPoints(int $tier,int $rank,int $finalistCount):float{
 $matrix=[
  1=>[1=>5,2=>4,3=>3,4=>2,5=>1],
  2=>[1=>10,2=>8,3=>6,4=>4,5=>2],
  3=>[1=>15,2=>12,3=>10,4=>8,5=>6],
 ];
 if(isset($matrix[$tier][$rank]))return(float)$matrix[$tier][$rank];
 if($tier===2&&$rank>=6&&$rank<=10)return 1;
 if($tier===3&&$rank>=6&&$rank<=$finalistCount)return 2;
 return 0;
}

function approvalTier(PDO $pdo,array $round):array{
 $stmt=$pdo->prepare("
  SELECT points_tier
  FROM bdc_event_points_tiers
  WHERE event_id=:event_id AND division=:division
  ORDER BY FIELD(dance_role,'both','leader','follower'),id
  LIMIT 1
 ");
 $stmt->execute([
  'event_id'=>$round['event_id'],
  'division'=>$round['division'],
 ]);
 $tier=(int)$stmt->fetchColumn();

 if(in_array($tier,[1,2,3],true)){
  return ['tier'=>$tier,'source'=>'Saved event/division tier'];
 }

 $tier=(int)$round['event_points_tier'];
 if(in_array($tier,[1,2,3],true)){
  return ['tier'=>$tier,'source'=>'Saved event tier'];
 }

 $heats=$pdo->prepare("
  SELECT id
  FROM bdc_test_scoring_rounds
  WHERE event_id=:event_id
    AND division=:division
    AND round_type='heats'
  ORDER BY id ASC
  LIMIT 1
 ");
 $heats->execute([
  'event_id'=>$round['event_id'],
  'division'=>$round['division'],
 ]);
 $heatsId=(int)$heats->fetchColumn();
 if($heatsId<1)throw new RuntimeException('Heats round not found. The BDC points tier cannot be determined.');

 $countStmt=$pdo->prepare("
  SELECT MAX(role_total)
  FROM (
   SELECT COUNT(*) AS role_total
   FROM bdc_test_scoring_entries
   WHERE round_id=:round_id AND entry_status='active'
   GROUP BY dance_role
  ) role_counts
 ");
 $countStmt->execute(['round_id'=>$heatsId]);
 $competitorCount=(int)$countStmt->fetchColumn();

 if($competitorCount<5)throw new RuntimeException('At least 5 competitors are required for a BDC points tier.');

 $tier=$competitorCount<=15?1:($competitorCount<=30?2:3);
 return [
  'tier'=>$tier,
  'source'=>'Automatically derived from '.$competitorCount.' Heats competitors',
 ];
}

function approvalPairs(PDO $pdo,int $roundId,int $tier):array{
 $stmt=$pdo->prepare("
  SELECT
   fp.id AS pair_id,
   fp.pair_number,
   le.competitor_id AS leader_competitor_id,
   le.display_name AS leader_name,
   le.bib_number AS leader_bib,
   fe.competitor_id AS follower_competitor_id,
   fe.display_name AS follower_name,
   fe.bib_number AS follower_bib,
   fr.final_rank
  FROM bdc_test_scoring_final_pairs fp
  JOIN bdc_test_scoring_entries le ON le.id=fp.leader_entry_id
  JOIN bdc_test_scoring_entries fe ON fe.id=fp.follower_entry_id
  JOIN bdc_test_scoring_final_results fr
    ON fr.round_id=fp.round_id
   AND fr.pair_id=fp.id
  WHERE fp.round_id=:round_id
    AND fp.pairing_status='confirmed'
  ORDER BY fr.final_rank
 ");
 $stmt->execute(['round_id'=>$roundId]);
 $pairs=$stmt->fetchAll();

 if(!$pairs)throw new RuntimeException('Calculate and submit Final rankings before requesting approval.');

 $count=count($pairs);
 foreach($pairs as &$pair){
  $pair['points']=approvalPoints($tier,(int)$pair['final_rank'],$count);
 }
 unset($pair);

 return $pairs;
}

function approvalHeatsId(PDO $pdo,array $round):int{
 $stmt=$pdo->prepare("
  SELECT id
  FROM bdc_test_scoring_rounds
  WHERE event_id=:event_id
    AND division=:division
    AND round_type='heats'
  ORDER BY id ASC
  LIMIT 1
 ");
 $stmt->execute([
  'event_id'=>$round['event_id'],
  'division'=>$round['division'],
 ]);
 return (int)$stmt->fetchColumn();
}

function loadPublication(PDO $pdo,int $roundId):?array{
 $stmt=$pdo->prepare("
  SELECT *
  FROM bdc_test_scoring_publications
  WHERE final_round_id=:round_id
  LIMIT 1
 ");
 $stmt->execute(['round_id'=>$roundId]);
 return $stmt->fetch()?:null;
}

function approvalAudit(PDO $pdo,int $roundId,int $userId,string $action,array $details=[]):void{
 $stmt=$pdo->prepare("
  INSERT INTO bdc_test_scoring_audit(round_id,user_id,action,details_json)
  VALUES(:round_id,:user_id,:action,:details)
 ");
 $stmt->execute([
  'round_id'=>$roundId,
  'user_id'=>$userId?:null,
  'action'=>$action,
  'details'=>json_encode($details,JSON_UNESCAPED_UNICODE),
 ]);
}


function repositorySafeName(string $name):string{$name=preg_replace('/[\\\\\\/\\:\\*\\?\\\"\\<\\>\\|]+/u','',$name)??'Event';$name=preg_replace('/\\s+/u',' ',trim($name))??'Event';return $name!==''?$name:'Event';}
function removeStoredPublicationFile(?string $path):void{
 if(!$path)return;

 $file=realpath(ResultStorageService::resolve($path)??'');
 if(!$file || !is_file($file))return;
 $base=realpath(ResultStorageService::root());
 if($base && str_starts_with($file,$base.DIRECTORY_SEPARATOR))@unlink($file);
}



function pendingHtmlDirectory(int $roundId):string{
 $session=session_id()?:'no-session';
 $safeSession=preg_replace('/[^A-Za-z0-9_-]/','',$session)?:'session';
 $directory=ResultStorageService::root().'/.pending-html/'.$safeSession.'/'.$roundId;

 if(!is_dir($directory) && !mkdir($directory,0700,true) && !is_dir($directory)){
  throw new RuntimeException('Could not create the temporary HTML archive folder.');
 }

 return $directory;
}

function validateArchivedHtml(string $path):void{
 if(!is_file($path) || filesize($path)<500){
  throw new RuntimeException('A generated HTML archive is missing or empty.');
 }

 $html=(string)file_get_contents($path);
 if(
  stripos($html,'<!doctype html')===false &&
  stripos($html,'<html')===false
 ){
  throw new RuntimeException('The generated repository file is not valid HTML.');
 }
}

function consumeArchivedHtml(
 int $roundId,
 string $eventName,
 string $eventDate
):array{
 $temporaryDirectory=pendingHtmlDirectory($roundId);
 $resultRoot=ResultStorageService::root();

 if(!is_dir($resultRoot) && !mkdir($resultRoot,0755,true) && !is_dir($resultRoot)){
  throw new RuntimeException('Could not create public/results.');
 }

 $archivedFiles=[];

 foreach([
  'heats'=>$eventName.'_Heats_'.$eventDate.'.html',
  'finals'=>$eventName.'_Final_'.$eventDate.'.html',
  'points'=>$eventName.'_Points_'.$eventDate.'.html',
 ] as $category=>$filename){
  $source=$temporaryDirectory.'/'.$category.'.html';
  validateArchivedHtml($source);

  $target=$resultRoot.'/'.$filename;
  if(is_file($target))@unlink($target);

  if(!rename($source,$target)){
   throw new RuntimeException('Could not move the '.$category.' HTML result into the repository.');
  }

  // Public repository files must be readable by Apache.
  if(!@chmod($target,0644)){
   throw new RuntimeException('Could not set 0644 permissions on the '.$category.' HTML result.');
  }

  $relative=ResultStorageService::relative($filename);
  $archivedFiles[$category]=[
   'url'=>ResultStorageService::publicUrl($filename),
   'storage_path'=>$relative,
   'absolute_path'=>$target,
   'size'=>filesize($target)?:0,
   'checksum'=>hash_file('sha256',$target)?:'',
  ];
 }

 @rmdir($temporaryDirectory);

 return $archivedFiles;
}

try{
 $round=loadApprovalRound($pdo,$roundId);
 $tierInfo=approvalTier($pdo,$round);
 $tier=(int)$tierInfo['tier'];
 $pairs=approvalPairs($pdo,$roundId,$tier);
 $heatsId=approvalHeatsId($pdo,$round);
 $publication=loadPublication($pdo,$roundId);

 if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!Csrf::verify($_POST['_csrf']??null)){
   throw new RuntimeException('Invalid security token.');
  }

  $action=(string)($_POST['action']??'');

  if($action==='submit_for_approval'){
   if(empty($_POST['accept_submit'])){
    throw new RuntimeException('Accept the approval submission warning first.');
   }
   if($round['status']!=='scores_submitted'){
    throw new RuntimeException('Final scores must be submitted before requesting approval.');
   }
   if($publication && $publication['status']==='published'){
    throw new RuntimeException('This competition is already published.');
   }

   $pdo->beginTransaction();
   try{
    if($publication){
     $pdo->prepare("
      UPDATE bdc_test_scoring_publications
      SET points_tier=:tier,
          status='pending_approval',
          submitted_by=:submitted_by,
          submitted_at=NOW(),
          rejected_by=NULL,
          rejected_at=NULL,
          rejection_reason=NULL,
          updated_at=NOW()
      WHERE id=:id
     ")->execute([
      'tier'=>$tier,
      'submitted_by'=>$userId?:null,
      'id'=>$publication['id'],
     ]);
     $publicationId=(int)$publication['id'];
    }else{
     $pdo->prepare("
      INSERT INTO bdc_test_scoring_publications(
       event_id,final_round_id,division,points_tier,status,submitted_by,submitted_at,published_at
      ) VALUES(
       :event_id,:round_id,:division,:tier,'pending_approval',:submitted_by,NOW(),NULL
      )
     ")->execute([
      'event_id'=>$round['event_id'],
      'round_id'=>$roundId,
      'division'=>$round['division'],
      'tier'=>$tier,
      'submitted_by'=>$userId?:null,
     ]);
     $publicationId=(int)$pdo->lastInsertId();
    }

    $pdo->prepare("
     UPDATE bdc_test_scoring_rounds
     SET status='pending_approval',
         publication_id=:publication_id,
         locked_at=NOW(),
         locked_by=:locked_by
     WHERE id=:round_id
    ")->execute([
     'publication_id'=>$publicationId,
     'locked_by'=>$userId?:null,
     'round_id'=>$roundId,
    ]);

    approvalAudit($pdo,$roundId,$userId,'competition_submitted_for_approval',[
     'publication_id'=>$publicationId,
     'points_tier'=>$tier,
    ]);

    $pdo->commit();
    $notice='Competition submitted for Super Admin approval. No points or repository result have been created yet.';
   }catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    throw $e;
   }
  }

  if($action==='approve_publication'){
   if(!$isSuperAdmin)throw new RuntimeException('Only Super Admin can approve publication.');

   $publication=loadPublication($pdo,$roundId);
   if(!$publication || $publication['status']!=='pending_approval'){
    throw new RuntimeException('No pending approval was found for this competition.');
   }
   if($round['status']!=='pending_approval'){
    throw new RuntimeException('The Final round is not awaiting approval.');
   }
   $heatsRoundId=approvalHeatsId($pdo,$round);
   if($heatsRoundId<1)throw new RuntimeException('Heats round was not found.');

   if(empty($_POST['client_html_ready'])){
    throw new RuntimeException('Generate the three archived HTML results before approval.');
   }

   $eventDate=(string)($round['event_date']?:date('Y-m-d'));
   $eventName=repositorySafeName((string)$round['event_name']);
   $archivedFiles=consumeArchivedHtml($roundId,$eventName,$eventDate);

   $pdo->beginTransaction();
   try{
    $transactionStmt=$pdo->prepare("
     INSERT INTO bdc_point_transactions(
      competitor_id,event_id,division,dance_role,points,placement,notes,
      source_type,source_row_hash,created_by
     ) VALUES(
      :competitor_id,:event_id,:division,:dance_role,:points,:placement,:notes,
      'scoring_engine',:source_hash,:created_by
     )
    ");

    $resultStmt=$pdo->prepare("
     INSERT INTO bdc_participant_results(
      event_id,competitor_id,division,dance_role,placement,finalist_status,
      partner_name,points_awarded,source,point_transaction_id
     ) VALUES(
      :event_id,:competitor_id,:division,:dance_role,:placement,:finalist_status,
      :partner_name,:points,'scoring_engine',:transaction_id
     )
    ");

    $linkStmt=$pdo->prepare("
     INSERT INTO bdc_test_scoring_publication_points(
      publication_id,pair_id,competitor_id,dance_role,final_rank,points_awarded,
      point_transaction_id,participant_result_id
     ) VALUES(
      :publication_id,:pair_id,:competitor_id,:dance_role,:final_rank,:points,
      :transaction_id,:participant_result_id
     )
    ");

    foreach($pairs as $pair){
     foreach([
      [
       'dance_role'=>'leader',
       'competitor_id'=>(int)$pair['leader_competitor_id'],
       'partner_name'=>$pair['follower_name'],
      ],
      [
       'dance_role'=>'follower',
       'competitor_id'=>(int)$pair['follower_competitor_id'],
       'partner_name'=>$pair['leader_name'],
      ],
     ] as $person){
      if($person['competitor_id']<1){
       throw new RuntimeException('Every finalist must be linked to a BDC competitor before approval.');
      }

      $rank=(int)$pair['final_rank'];
      $points=(float)$pair['points'];
      $placement=approvalOrdinal($rank);
      $sourceHash=hash(
       'sha256',
       'scoring-publication|'.$publication['id'].'|'.$person['dance_role'].'|'.$person['competitor_id']
      );

      $transactionStmt->execute([
       'competitor_id'=>$person['competitor_id'],
       'event_id'=>$round['event_id'],
       'division'=>$round['division'],
       'dance_role'=>$person['dance_role'],
       'points'=>$points,
       'placement'=>$placement,
       'notes'=>'BDC scoring publication #'.$publication['id'],
       'source_hash'=>$sourceHash,
       'created_by'=>$userId?:null,
      ]);
      $transactionId=(int)$pdo->lastInsertId();

      $finalistStatus=$rank===1?'winner':($rank<=5?'placed':'finalist');
      $resultStmt->execute([
       'event_id'=>$round['event_id'],
       'competitor_id'=>$person['competitor_id'],
       'division'=>$round['division'],
       'dance_role'=>$person['dance_role'],
       'placement'=>$placement,
       'finalist_status'=>$finalistStatus,
       'partner_name'=>$person['partner_name'],
       'points'=>$points,
       'transaction_id'=>$transactionId,
      ]);
      $participantResultId=(int)$pdo->lastInsertId();

      $linkStmt->execute([
       'publication_id'=>$publication['id'],
       'pair_id'=>$pair['pair_id'],
       'competitor_id'=>$person['competitor_id'],
       'dance_role'=>$person['dance_role'],
       'final_rank'=>$rank,
       'points'=>$points,
       'transaction_id'=>$transactionId,
       'participant_result_id'=>$participantResultId,
      ]);
     }
    }
    $docs=[
     [
      'category'=>'heats',
      'title'=>'HEATS RESULTS — '.$round['event_name'].' ('.ucfirst($round['division']).')',
      'file'=>$archivedFiles['heats'],
     ],
     [
      'category'=>'finals',
      'title'=>'FINAL RESULTS — '.$round['event_name'].' ('.ucfirst($round['division']).')',
      'file'=>$archivedFiles['finals'],
     ],
     [
      'category'=>'points',
      'title'=>'FINAL RANKING & POINTS — '.$round['event_name'].' ('.ucfirst($round['division']).')',
      'file'=>$archivedFiles['points'],
     ],
    ];

    $ds=$pdo->prepare("
     INSERT INTO bdc_test_result_documents(
      event_id,title,document_category,file_type,url,storage_path,status,source,created_by
     ) VALUES(
      :event_id,:title,:category,'external',:url,:storage_path,'published','scoring_engine',:created_by
     )
    ");

    $map=$pdo->prepare("
     INSERT INTO bdc_scoring_publication_documents(
      publication_id,document_category,repository_document_id,storage_path
     ) VALUES(
      :publication_id,:category,:document_id,:storage_path
     )
    ");

    $documentIds=[];
    foreach($docs as $document){
     $ds->execute([
      'event_id'=>$round['event_id'],
      'title'=>$document['title'],
      'category'=>$document['category'],
      'url'=>$document['file']['url'],
      'storage_path'=>$document['file']['storage_path'],
      'created_by'=>$userId?:null,
     ]);

     $newDocumentId=(int)$pdo->lastInsertId();
     $documentIds[$document['category']]=$newDocumentId;

     $map->execute([
      'publication_id'=>$publication['id'],
      'category'=>$document['category'],
      'document_id'=>$newDocumentId,
      'storage_path'=>$document['file']['storage_path'],
     ]);
    }

    $documentId=$documentIds['finals'];
    $reportUrl=$archivedFiles['points']['url'];

    $pdo->prepare("
     UPDATE bdc_test_scoring_publications
     SET status='published',
         repository_document_id=:document_id,
         report_url=:report_url,
         published_by=:published_by,
         approved_by=:approved_by,
         approved_at=NOW(),
         published_at=NOW(),
         updated_at=NOW()
     WHERE id=:publication_id
    ")->execute([
     'document_id'=>$documentId,
     'report_url'=>$reportUrl,
     'published_by'=>$userId?:null,
     'approved_by'=>$userId?:null,
     'publication_id'=>$publication['id'],
    ]);

    $pdo->prepare("UPDATE bdc_test_events SET status='completed',updated_at=NOW() WHERE id=:event_id")->execute(['event_id'=>$round['event_id']]);

    $pdo->prepare("
     UPDATE bdc_test_scoring_rounds
     SET status='archived',
         published_document_id=:document_id,
         locked_at=NOW(),
         locked_by=:locked_by
     WHERE id=:round_id
    ")->execute([
     'document_id'=>$documentId,
     'locked_by'=>$userId?:null,
     'round_id'=>$roundId,
    ]);

    approvalAudit($pdo,$roundId,$userId,'competition_approved_and_published',[
     'publication_id'=>$publication['id'],
     'document_id'=>$documentId,
     'points_tier'=>$tier,
    ]);

    $pdo->commit();
    $notice='Approved successfully. Points were added, the repository result was published, and scoring was archived.';
   }catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    foreach($archivedFiles??[] as $archivedFile){
     if(!empty($archivedFile['absolute_path']) && is_file($archivedFile['absolute_path'])){
      @unlink($archivedFile['absolute_path']);
     }
    }
    throw $e;
   }
  }

  if($action==='reject_approval'){
   if(!$isSuperAdmin)throw new RuntimeException('Only Super Admin can reject an approval request.');

   $reason=trim((string)($_POST['rejection_reason']??''));
   if($reason==='')throw new RuntimeException('Rejection reason is required.');

   $publication=loadPublication($pdo,$roundId);
   if(!$publication || $publication['status']!=='pending_approval'){
    throw new RuntimeException('No pending approval was found.');
   }

   $pdo->beginTransaction();
   try{
    $pdo->prepare("
     UPDATE bdc_test_scoring_publications
     SET status='rejected',
         rejected_by=:rejected_by,
         rejected_at=NOW(),
         rejection_reason=:reason,
         updated_at=NOW()
     WHERE id=:publication_id
    ")->execute([
     'rejected_by'=>$userId?:null,
     'reason'=>$reason,
     'publication_id'=>$publication['id'],
    ]);

    $pdo->prepare("
     UPDATE bdc_test_scoring_rounds
     SET status='scores_submitted',
         locked_at=NULL,
         locked_by=NULL
     WHERE id=:round_id
    ")->execute(['round_id'=>$roundId]);

    approvalAudit($pdo,$roundId,$userId,'competition_approval_rejected',[
     'publication_id'=>$publication['id'],
     'reason'=>$reason,
    ]);

    $pdo->commit();
    $notice='Approval request rejected. Final scoring has been reopened for correction.';
   }catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    throw $e;
   }
  }

  if($action==='rollback'){
   if(!$isSuperAdmin)throw new RuntimeException('Only Super Admin can roll back a published competition.');

   $reason=trim((string)($_POST['rollback_reason']??''));
   if($reason==='')throw new RuntimeException('Rollback reason is required.');

   $publication=loadPublication($pdo,$roundId);
   if(!$publication || $publication['status']!=='published'){
    throw new RuntimeException('An active published competition was not found.');
   }

   $linksStmt=$pdo->prepare("
    SELECT *
    FROM bdc_test_scoring_publication_points
    WHERE publication_id=:publication_id
   ");
   $linksStmt->execute(['publication_id'=>$publication['id']]);
   $links=$linksStmt->fetchAll();

   $pdo->beginTransaction();
   try{
    foreach($links as $link){
     if(!empty($link['participant_result_id'])){
      $pdo->prepare("DELETE FROM bdc_participant_results WHERE id=:id")
          ->execute(['id'=>$link['participant_result_id']]);
     }
     if(!empty($link['point_transaction_id'])){
      $pdo->prepare("DELETE FROM bdc_point_transactions WHERE id=:id")
          ->execute(['id'=>$link['point_transaction_id']]);
     }
    }

    $pdo->prepare("
     DELETE FROM bdc_test_scoring_publication_points
     WHERE publication_id=:publication_id
    ")->execute(['publication_id'=>$publication['id']]);

    $pd=$pdo->prepare("SELECT repository_document_id,storage_path FROM bdc_scoring_publication_documents WHERE publication_id=:publication_id");
    $pd->execute(['publication_id'=>$publication['id']]);$publicationDocumentRows=$pd->fetchAll();
    foreach($publicationDocumentRows as $d){$pdo->prepare("DELETE FROM bdc_test_result_documents WHERE id=:id")->execute(['id'=>$d['repository_document_id']]);}
    $pdo->prepare("DELETE FROM bdc_scoring_publication_documents WHERE publication_id=:publication_id")->execute(['publication_id'=>$publication['id']]);

    $pdo->prepare("
     UPDATE bdc_test_scoring_publications
     SET status='rolled_back',
         repository_document_id=NULL,
         report_url=NULL,
         rolled_back_by=:rolled_back_by,
         rolled_back_at=NOW(),
         rollback_reason=:reason,
         updated_at=NOW()
     WHERE id=:publication_id
    ")->execute([
     'rolled_back_by'=>$userId?:null,
     'reason'=>$reason,
     'publication_id'=>$publication['id'],
    ]);

    $pdo->prepare("
     UPDATE bdc_test_scoring_rounds
     SET status='scores_submitted',
         publication_id=NULL,
         published_document_id=NULL,
         locked_at=NULL,
         locked_by=NULL
     WHERE id=:round_id
    ")->execute(['round_id'=>$roundId]);

    approvalAudit($pdo,$roundId,$userId,'competition_publication_rolled_back',[
     'publication_id'=>$publication['id'],
     'reason'=>$reason,
    ]);

    $pdo->commit();
    foreach($publicationDocumentRows??[] as $d){removeStoredPublicationFile($d['storage_path']??null);}
    $notice='Rollback completed. Heats, Final and Points PDFs, points and repository records were removed. Final scoring was reopened.';
   }catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    throw $e;
   }
  }

  $round=loadApprovalRound($pdo,$roundId);
  $tierInfo=approvalTier($pdo,$round);
  $tier=(int)$tierInfo['tier'];
  $pairs=approvalPairs($pdo,$roundId,$tier);
  $heatsId=approvalHeatsId($pdo,$round);
  $publication=loadPublication($pdo,$roundId);
 }
}catch(Throwable $e){
 $error=$e->getMessage();
 $round=$round??null;
 $pairs=$pairs??[];
 $tier=$tier??0;
 $tierInfo=$tierInfo??['source'=>'Unavailable'];
 $heatsId=$heatsId??0;
 $publication=$publication??null;
}

$csrf=Csrf::token();
$status=(string)($publication['status']??'not_submitted');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Competition Approval</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f5f6f8}
.review-shell{max-width:1180px}
.review-card{border:0;border-radius:14px;box-shadow:0 5px 20px rgba(15,23,42,.07)}
.review-title{border-left:5px solid #ff6500;padding-left:14px}
.points-table td,.points-table th{vertical-align:middle}
.couple-name{font-weight:700;white-space:nowrap}
.preview-btn{min-width:180px}
.tier-card{background:#fff7ed;border:1px solid #fed7aa}
.submit-card{background:#fff;border:2px solid #0d6efd}
.approval-card{background:#fff;border:2px solid #198754}
.rollback-card{background:#fff;border:2px solid #dc3545}
@media(max-width:575.98px){
 .modal-dialog{margin:.5rem}
 .modal-content{max-height:calc(100dvh - 1rem)}
 .modal-body{overflow-y:auto}
 .modal-footer{flex-wrap:wrap}
 .modal-footer form,.modal-footer form .btn{width:100%}
}
</style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark">
 <div class="container-fluid">
  <a class="navbar-brand" href="../">BDC Admin</a>
  <a class="btn btn-outline-light btn-sm" href="index.php?round_id=<?=$roundId?>">Back to Final Dashboard</a>
 </div>
</nav>

<main class="container review-shell py-4">
 <div class="review-title mb-4">
  <h1 class="h3 mb-1">Competition Approval &amp; Publication</h1>
  <p class="text-muted mb-0">
   <?=is_array($round)?e($round['event_name']).' · '.e(ucfirst($round['division'])).' · Final':''?>
  </p>
 </div>

 <?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
 <?php if($notice):?><div class="alert alert-success"><?=e($notice)?></div><?php endif;?>

 <?php if(is_array($round)):?>
 <section class="card review-card mb-4">
  <div class="card-body">
   <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
    <div>
     <h2 class="h5">Review Scoring</h2>
     <p class="text-muted mb-0">Review Heats, Final scores and the points report.</p>
    </div>
    <?php
     $statusClass=match($status){
      'pending_approval'=>'text-bg-warning',
      'published'=>'text-bg-success',
      'rejected'=>'text-bg-danger',
      'rolled_back'=>'text-bg-secondary',
      default=>'text-bg-primary'
     };
     $statusLabel=match($status){
      'pending_approval'=>'Pending Super Admin Approval',
      'published'=>'Published & Archived',
      'rejected'=>'Rejected — Corrections Required',
      'rolled_back'=>'Rolled Back',
      default=>'Ready to Submit'
     };
    ?>
    <span class="badge <?=$statusClass?> fs-6"><?=e($statusLabel)?></span>
   </div>

   <div class="d-flex flex-wrap gap-2 mt-3">
    <?php if($heatsId):?>
     <a class="btn btn-outline-primary preview-btn" target="_blank" href="result.php?round_id=<?=$heatsId?>">Preview Heats Scores</a>
    <?php endif;?>
    <a class="btn btn-outline-primary preview-btn" target="_blank" href="final-result.php?round_id=<?=$roundId?>">Preview Final Scores</a>
    <a class="btn btn-outline-dark preview-btn" target="_blank" href="publication-report.php?round_id=<?=$roundId?>">Preview Points Report</a>
   </div>
  </div>
 </section>

 <section class="card review-card tier-card mb-4">
  <div class="card-body">
   <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
    <div>
     <h2 class="h5 mb-1">BDC Points Tier</h2>
     <p class="mb-0">Confirmed from the event/scoring setup.</p>
    </div>
    <span class="badge text-bg-warning fs-6 px-3 py-2">Tier <?=$tier?></span>
   </div>
   <div class="mt-3 small text-muted">
    <strong>Division:</strong> <?=e(ucfirst($round['division']))?>
    <span class="mx-2">•</span>
    <strong>Source:</strong> <?=e((string)$tierInfo['source'])?>
   </div>
  </div>
 </section>

 <section class="card review-card mb-4">
  <div class="card-body">
   <h2 class="h5 mb-1">Points Allocation</h2>
   <p class="text-muted">No points are written to the database until Super Admin approval.</p>
   <div class="table-responsive">
    <table class="table table-bordered points-table mb-0">
     <thead class="table-light">
      <tr>
       <th>Rank</th>
       <th>Final Couple</th>
       <th>Leader Points</th>
       <th>Follower Points</th>
      </tr>
     </thead>
     <tbody>
     <?php foreach($pairs as $pair):?>
      <tr>
       <td><strong><?=e(approvalOrdinal((int)$pair['final_rank']))?></strong></td>
       <td class="couple-name"><?=e($pair['leader_name'])?> &amp; <?=e($pair['follower_name'])?></td>
       <td><strong><?=number_format((float)$pair['points'],1)?></strong> to <?=e($pair['leader_name'])?></td>
       <td><strong><?=number_format((float)$pair['points'],1)?></strong> to <?=e($pair['follower_name'])?></td>
      </tr>
     <?php endforeach;?>
     </tbody>
    </table>
   </div>
  </div>
 </section>

 <?php if(in_array($status,['not_submitted','rejected','rolled_back'],true) && $round['status']==='scores_submitted'):?>
 <section class="card review-card submit-card">
  <div class="card-body">
   <h2 class="h5 text-primary">Submit for Super Admin Approval</h2>
   <div class="alert alert-info">
    This submission does not update points or publish a repository result. It locks scoring while Super Admin reviews the competition.
   </div>
   <label class="form-check mb-3">
    <input class="form-check-input" id="submitAccept" type="checkbox">
    <span class="form-check-label">I reviewed Heats, Final scores and Points Allocation, and I am ready to submit for approval.</span>
   </label>
   <button class="btn btn-primary btn-lg" id="openSubmitModal" type="button" data-bs-toggle="modal" data-bs-target="#submitModal" disabled>
    Submit for Super Admin Approval
   </button>
  </div>
 </section>
 <?php endif;?>

 <?php if($status==='pending_approval'):?>
 <div class="alert alert-warning">
  <strong>Pending approval:</strong> No points or repository result have been created. Scoring is locked until Super Admin approves or rejects this request.
 </div>

 <?php if($isSuperAdmin):?>
 <section class="card review-card approval-card mb-4">
  <div class="card-body">
   <h2 class="h5 text-success">Super Admin Approval</h2>
   <p>Approve only after checking all previews and points allocation.</p>
   <div class="d-flex gap-2 flex-wrap">
    <button class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#approvalModal">
     Approve, Publish &amp; Update Points
    </button>
    <button class="btn btn-outline-danger btn-lg" data-bs-toggle="collapse" data-bs-target="#rejectPanel">
     Reject &amp; Reopen
    </button>
   </div>
   <div class="collapse mt-3" id="rejectPanel">
    <form method="post">
     <input type="hidden" name="_csrf" value="<?=e($csrf)?>">
     <input type="hidden" name="action" value="reject_approval">
     <input type="hidden" name="round_id" value="<?=$roundId?>">
     <label class="form-label">Rejection reason</label>
     <textarea class="form-control mb-2" name="rejection_reason" required></textarea>
     <button class="btn btn-danger">Confirm Rejection and Reopen Scoring</button>
    </form>
   </div>
  </div>
 </section>
 <?php endif;?>
 <?php endif;?>

 <?php if($status==='published'):?>
 <div class="alert alert-success">
  <strong>Published and archived:</strong> BDC points were updated and the repository result was created.
 </div>

 <?php if($isSuperAdmin):?>
 <section class="card review-card rollback-card">
  <div class="card-body">
   <h2 class="h5 text-danger">Super Admin Rollback</h2>
   <p>Rollback affects only this competition and removes only records created by this publication.</p>
   <form method="post" onsubmit="return confirm('Remove publication-generated points and repository result, then reopen Final scoring?');">
    <input type="hidden" name="_csrf" value="<?=e($csrf)?>">
    <input type="hidden" name="action" value="rollback">
    <input type="hidden" name="round_id" value="<?=$roundId?>">
    <textarea class="form-control mb-2" name="rollback_reason" placeholder="Rollback reason" required></textarea>
    <button class="btn btn-outline-danger">Roll Back This Competition</button>
   </form>
  </div>
 </section>
 <?php endif;?>
 <?php endif;?>
 <?php endif;?>
</main>

<div class="modal fade" id="submitModal" tabindex="-1" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered">
  <div class="modal-content">
   <div class="modal-header bg-primary text-white">
    <h2 class="modal-title fs-5">Submit for Super Admin Approval</h2>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
   </div>
   <div class="modal-body">
    <p><strong>After submission:</strong></p>
    <ul>
     <li>Scoring becomes temporarily read-only.</li>
     <li>No BDC points are added yet.</li>
     <li>No repository result is published yet.</li>
     <li>Super Admin must approve or reject the competition.</li>
    </ul>
   </div>
   <div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
    <form method="post">
     <input type="hidden" name="_csrf" value="<?=e($csrf)?>">
     <input type="hidden" name="action" value="submit_for_approval">
     <input type="hidden" name="round_id" value="<?=$roundId?>">
     <input type="hidden" name="accept_submit" value="1">
     <button class="btn btn-primary">Confirm Submission</button>
    </form>
   </div>
  </div>
 </div>
</div>

<div class="modal fade" id="approvalModal" tabindex="-1" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered">
  <div class="modal-content">
   <div class="modal-header bg-success text-white">
    <h2 class="modal-title fs-5">Permanent Approval Confirmation</h2>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
   </div>
   <div class="modal-body">
    <p><strong>This action will permanently:</strong></p>
    <ul>
     <li>Add the displayed BDC points to every eligible Leader and Follower.</li>
     <li>Publish the Final result to the Result Repository.</li>
     <li>Archive and lock this scoring workflow.</li>
    </ul>
    <div class="alert alert-warning">
     Approval stores permanent read-only HTML copies of the reviewed Heats, Final and Points pages in the existing Result Repository.
    </div>
    <div id="htmlGenerationStatus" class="small mt-2 text-muted">
     Archived Heats, Final and Points results will be created automatically when you approve.
    </div>
   </div>
   <div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
    <form method="post">
     <input type="hidden" name="_csrf" value="<?=e($csrf)?>">
     <input type="hidden" name="action" value="approve_publication">
     <input type="hidden" name="round_id" value="<?=$roundId?>">
     <input type="hidden" name="client_html_ready" id="clientHtmlReady" value="0">
     <button class="btn btn-success" id="finalApproveButton" type="submit">
      Approve, Publish &amp; Update Points
     </button>
    </form>
   </div>
  </div>
 </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


<script>
const submitAccept=document.getElementById('submitAccept');
const openSubmitModal=document.getElementById('openSubmitModal');
if(submitAccept&&openSubmitModal){
 submitAccept.addEventListener('change',()=>{
  openSubmitModal.disabled=!submitAccept.checked;
 });
}


const htmlGenerationStatus=document.getElementById('htmlGenerationStatus');
const finalApproveButton=document.getElementById('finalApproveButton');
const clientHtmlReady=document.getElementById('clientHtmlReady');

function makeArchivedHtml(sourceHtml,sourceUrl,officialLabel){
 const parser=new DOMParser();
 const documentCopy=parser.parseFromString(sourceHtml,'text/html');

 documentCopy.querySelectorAll(
  'nav,.toolbar,.no-print,button,form,script'
 ).forEach(element=>element.remove());

 const base=documentCopy.createElement('base');
 base.href=new URL(sourceUrl,window.location.href).href;
 documentCopy.head.prepend(base);

 documentCopy.title=documentCopy.title
  .replace(/Draft Result/gi,'Official Result')
  .replace(/· Draft$/gi,'· Official');

 documentCopy.body.innerHTML=documentCopy.body.innerHTML
  .replace(/DRAFT RESULT/gi,'OFFICIAL RESULT')
  .replace(/· DRAFT(?! RESULT)/gi,'· OFFICIAL');

 const style=documentCopy.createElement('style');
 style.textContent=`
  .repository-archive-banner{
   font-family:Arial,sans-serif;
   background:#111827;
   color:#fff;
   padding:10px 16px;
   font-size:13px;
   display:flex;
   justify-content:space-between;
   align-items:center;
   gap:12px;
  }
  .repository-archive-banner strong{font-size:14px}
  @media print{body{background:#fff!important}}
 `;
 documentCopy.head.appendChild(style);

 const banner=documentCopy.createElement('div');
 banner.className='repository-archive-banner';
 banner.innerHTML='<strong>BDC Official Archived Result</strong><span>Read-only repository snapshot</span>';
 documentCopy.body.prepend(banner);

 return '<!doctype html>\n'+documentCopy.documentElement.outerHTML;
}

async function fetchPreviewHtml(url){
 const response=await fetch(url,{
  credentials:'same-origin',
  cache:'no-store',
  headers:{'X-BDC-Browser-Archive':'1'}
 });

 if(!response.ok){
  throw new Error(`Could not open preview (${response.status}).`);
 }

 return await response.text();
}

async function uploadArchivedHtml(category,html){
 const form=new FormData();
 form.append('_csrf','<?=e($csrf)?>');
 form.append('round_id','<?=$roundId?>');
 form.append('category',category);
 form.append(
  'html',
  new Blob([html],{type:'text/html;charset=utf-8'}),
  category+'.html'
 );

 const response=await fetch('client-html-upload.php',{
  method:'POST',
  body:form,
  credentials:'same-origin'
 });

 const result=await response.json();

 if(!response.ok || !result.ok){
  throw new Error(result.error||'HTML archive upload failed.');
 }

 return result;
}

const approvalForm=finalApproveButton?finalApproveButton.closest('form'):null;
let approvalArchiveRunning=false;

async function generateAllArchivedHtml(){
 const previews=[
  ['heats','result.php?round_id=<?=$heatsId?>'],
  ['finals','final-result.php?round_id=<?=$roundId?>'],
  ['points','publication-report.php?round_id=<?=$roundId?>']
 ];
 for(let index=0;index<previews.length;index++){
  const [category,url]=previews[index];
  htmlGenerationStatus.className='small mt-2 text-primary';
  htmlGenerationStatus.textContent=`Preparing ${index+1} of 3: ${category}…`;
  const sourceHtml=await fetchPreviewHtml(url);
  const archivedHtml=makeArchivedHtml(sourceHtml,url,category);
  htmlGenerationStatus.textContent=`Saving ${index+1} of 3: ${category}…`;
  await uploadArchivedHtml(category,archivedHtml);
 }
}

if(approvalForm){
 approvalForm.addEventListener('submit',async event=>{
  if(clientHtmlReady.value==='1' || approvalArchiveRunning)return;
  event.preventDefault();
  approvalArchiveRunning=true;
  finalApproveButton.disabled=true;
  try{
   await generateAllArchivedHtml();
   clientHtmlReady.value='1';
   htmlGenerationStatus.className='small mt-2 text-success';
   htmlGenerationStatus.textContent='Archived results ready. Publishing competition…';
   approvalForm.requestSubmit();
  }catch(error){
   clientHtmlReady.value='0';
   htmlGenerationStatus.className='small mt-2 text-danger';
   htmlGenerationStatus.textContent=error.message||'Could not create archived HTML results.';
   finalApproveButton.disabled=false;
   approvalArchiveRunning=false;
  }
 });
}
</script>
</body>
</html>
