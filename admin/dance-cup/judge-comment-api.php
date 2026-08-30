<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Csrf;
use App\Core\Database;
use App\Services\DanceCupJudgingPanelService;
use App\Services\DanceCupScoringService;
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$pdo=Database::connection();$test=(string)($_GET['data_mode']??$_POST['data_mode']??'')==='test';$p=$test?'bdc_test_dance_cup':'bdc_dance_cup';$token=preg_replace('/[^a-f0-9]/','',(string)($_GET['token']??$_POST['token']??''));$categoryId=(int)($_GET['category_id']??$_POST['category_id']??0);
try{
 if(strlen($token)!==64)throw new RuntimeException('Judge link not found.');
 DanceCupScoringService::ensureWorkspaceTables($pdo,$test);
 $q=$pdo->prepare("SELECT id,competition_id,judge_assignment_id,status FROM {$p}_judge_sessions WHERE access_token=:token LIMIT 1");$q->execute(['token'=>$token]);$session=$q->fetch();
 if(!$session){$categories=DanceCupJudgingPanelService::categorySessionsForToken($pdo,$token,$test);foreach($categories as $candidate)if($categoryId<1||(int)$candidate['competition_id']===$categoryId){$session=$candidate;break;}}
 if(!$session)throw new RuntimeException('Judge session is unavailable.');
 $competition=(int)$session['competition_id'];$judge=(int)$session['judge_assignment_id'];$locked=(string)$session['status']==='submitted';
 if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Security check failed. Reload and try again.');
  if($locked)throw new RuntimeException('Submitted comments are locked.');
  $entry=(int)($_POST['entry_id']??0);$comment=trim((string)($_POST['comment']??''));if(strlen($comment)>4000)throw new RuntimeException('Comment is too long.');
  $valid=$pdo->prepare("SELECT COUNT(*) FROM {$p}_entries WHERE id=:entry AND competition_id=:competition AND status='active'");$valid->execute(['entry'=>$entry,'competition'=>$competition]);if(!(int)$valid->fetchColumn())throw new RuntimeException('Contestant is unavailable.');
  if($comment==='')$pdo->prepare("DELETE FROM {$p}_judge_comments WHERE competition_id=:competition AND entry_id=:entry AND judge_id=:judge")->execute(['competition'=>$competition,'entry'=>$entry,'judge'=>$judge]);
  else $pdo->prepare("INSERT INTO {$p}_judge_comments(competition_id,entry_id,judge_id,private_comment) VALUES(:competition,:entry,:judge,:comment) ON DUPLICATE KEY UPDATE private_comment=VALUES(private_comment),updated_at=NOW()")->execute(['competition'=>$competition,'entry'=>$entry,'judge'=>$judge,'comment'=>$comment]);
  echo json_encode(['ok'=>true,'message'=>'Private comment saved.'],JSON_UNESCAPED_SLASHES);exit;
 }
 $comments=$pdo->prepare("SELECT entry_id,private_comment FROM {$p}_judge_comments WHERE competition_id=:competition AND judge_id=:judge");$comments->execute(['competition'=>$competition,'judge'=>$judge]);$values=[];foreach($comments->fetchAll() as $row)$values[(int)$row['entry_id']]=$row['private_comment'];
 echo json_encode(['ok'=>true,'comments'=>$values,'locked'=>$locked],JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
}catch(Throwable $e){http_response_code(400);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);}
