<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;use App\Core\Csrf;use App\Core\Database;

Auth::requireAdmin();
if(!Auth::isSuperAdmin()){http_response_code(403);exit('Only the Super Admin can archive or restore scoring rounds.');}
if($_SERVER['REQUEST_METHOD']!=='POST'||!Csrf::verify($_POST['_csrf']??null)){http_response_code(419);exit('Invalid security token.');}
$pdo=Database::connection();$roundId=(int)($_POST['round_id']??0);$dataMode=(string)($_POST['data_mode']??'live');$action=(string)($_POST['archive_action']??'archive');$mode=in_array((string)($_POST['mode']??''),['manual','automated'],true)?(string)$_POST['mode']:'manual';
if($roundId<1||!in_array($dataMode,['live','test'],true)||!in_array($action,['archive','restore'],true)){http_response_code(400);exit('Invalid archive request.');}
$table=$dataMode==='test'?'bdc_test_scoring_rounds':'bdc_scoring_rounds';$userId=(int)(Auth::user()['id']??0);
try{
 $pdo->beginTransaction();$stmt=$pdo->prepare("SELECT id,event_id,status,archived_from_status FROM {$table} WHERE id=:id FOR UPDATE");$stmt->execute(['id'=>$roundId]);$round=$stmt->fetch();if(!$round)throw new RuntimeException('Scoring round not found.');
 if($action==='archive'){
  if((string)$round['status']==='archived')throw new RuntimeException('This scoring round is already archived.');
  $pdo->prepare("UPDATE {$table} SET archived_from_status=status,status='archived',archived_at=NOW(),archived_by=:user WHERE id=:id")->execute(['user'=>$userId?:null,'id'=>$roundId]);
 }else{
  if((string)$round['status']!=='archived')throw new RuntimeException('This scoring round is not archived.');$restore=(string)($round['archived_from_status']?:'draft');
  $pdo->prepare("UPDATE {$table} SET status=:status,archived_from_status=NULL,archived_at=NULL,archived_by=NULL WHERE id=:id")->execute(['status'=>$restore,'id'=>$roundId]);
 }
 Auth::audit($userId,'scoring_round_'.$action,['data_mode'=>$dataMode,'previous_status'=>$round['status'],'restored_status'=>$action==='restore'?($restore??'draft'):null],'scoring_round',$roundId);$pdo->commit();
 $query=$dataMode==='test'?'legacy=1&test_mode='.rawurlencode($mode):'mode='.rawurlencode($mode);if($action==='archive')$query.='&round_archived=1';else$query.='&round_restored=1&archived=1';header('Location: '.($dataMode==='test'?'../scoring-tests/index.php':'index.php').'?'.$query,true,303);exit;
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();http_response_code(422);echo '<!doctype html><html><body style="font-family:Arial;padding:30px"><h2>Archive action blocked</h2><p>'.htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8').'</p><p><a href="'.($dataMode==='test'?'../scoring-tests/index.php?legacy=1&amp;test_mode=':'index.php?mode=').htmlspecialchars($mode,ENT_QUOTES,'UTF-8').'">Back to Scoring Dashboard</a></p></body></html>';exit;}
