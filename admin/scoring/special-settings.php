<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;use App\Core\Csrf;use App\Core\Database;use App\Services\SpecialCategoryService;
Auth::requireAdmin();$pdo=Database::connection();$roundId=(int)($_POST['round_id']??0);$return=(string)($_POST['return_to']??'index.php');
if(!in_array($return,['index.php','automatic-round.php'],true))$return='index.php';$automatic=$return==='automatic-round.php';
try{
 if($_SERVER['REQUEST_METHOD']!=='POST'||!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Invalid security token.');
 $stmt=$pdo->prepare('SELECT division FROM bdc_scoring_rounds WHERE id=:id');$stmt->execute(['id'=>$roundId]);$division=(string)$stmt->fetchColumn();if(!SpecialCategoryService::isSpecial($division))throw new RuntimeException('Special-category round not found.');
 $marks=$pdo->prepare("SELECT COUNT(*) FROM bdc_scoring_marks WHERE round_id=:id AND (mark_type<>'blank' OR weighted_score>0)");$marks->execute(['id'=>$roundId]);if((int)$marks->fetchColumn()>0)throw new RuntimeException('The YES tier cannot be changed or unlocked after judging has started.');
 $action=(string)($_POST['action']??'');
 if($action==='special_settings_lock'){$yes=(int)($_POST['special_yes_count']??0);if(!in_array($yes,[5,10,15],true))throw new RuntimeException('Select 5, 10 or 15 YES per judge.');$pdo->prepare('UPDATE bdc_scoring_rounds SET yes_count=:yes,callback_count=:yes,tier_manual_override=1,yes_weight=10.00,alt1_weight=4.50,alt2_weight=4.30,alt3_weight=4.20 WHERE id=:id')->execute(['yes'=>$yes,'id'=>$roundId]);$message='YES tier saved and locked at '.$yes.' per judge.';}
 elseif($action==='special_settings_unlock'){$pdo->prepare('UPDATE bdc_scoring_rounds SET tier_manual_override=0 WHERE id=:id')->execute(['id'=>$roundId]);$message='YES tier unlocked.';}
 else throw new RuntimeException('Invalid special-category settings action.');
 $_SESSION[$automatic?'automatic_scoring_notice':'scoring_notice']=$message;
}catch(Throwable $error){$_SESSION[$automatic?'automatic_scoring_error':'scoring_error']=$error->getMessage();}
header('Location: '.$return.'?round_id='.$roundId,true,303);exit;
