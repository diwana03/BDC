<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\ScoringCalculationService;
use App\Services\SpecialCategoryService;
use App\Services\TestCompetitorGeneratorService;
Auth::requireAdmin();
$pdo=Database::connection();
$userId=(int)(Auth::user()['id']??0);
$testMode=(string)($_GET['test_mode']??$_POST['test_mode']??$_SESSION['bdc_test_scoring_mode']??'manual');
if(!in_array($testMode,['manual','automated'],true))$testMode='manual';
$_SESSION['bdc_test_scoring_mode']=$testMode;
function testWorkspaceUrl(string $mode,int $roundId=0,array $extra=[]):string{$q=['test_mode'=>$mode];if($roundId>0)$q['round_id']=$roundId;foreach($extra as $k=>$v)$q[$k]=$v;return url('admin/scoring-tests/workspace.php?'.http_build_query($q));}
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
 if(!Csrf::verify($_POST['_csrf']??null)){http_response_code(419);exit('Invalid security token.');}
 $action=(string)($_POST['action']??'');$rid=(int)($_POST['round_id']??0);
 if($action==='generate_test_competitors'){
  TestCompetitorGeneratorService::generate($pdo,$rid,(int)($_POST['leader_count']??10),(int)($_POST['follower_count']??10),$userId);
  header('Location: '.testWorkspaceUrl($testMode,$rid,['competitors_generated'=>1]),true,303);exit;
 }
 if($action==='generate_results'){
  if($rid<1){http_response_code(400);exit('Invalid scoring round.');}
  ScoringCalculationService::calculateHeats($pdo,$rid,ScoringCalculationService::TEST,$userId);
  header('Location: '.testWorkspaceUrl($testMode,$rid,['shared_engine'=>1]),true,303);exit;
 }
 if($action==='settings'&&$rid>0){
  $s=$pdo->prepare('SELECT division FROM bdc_test_scoring_rounds WHERE id=:r');$s->execute(['r'=>$rid]);$division=(string)$s->fetchColumn();
  if(SpecialCategoryService::isSpecial($division)){
   $intent=(string)($_POST['special_settings_intent']??'lock');
   $marks=$pdo->prepare("SELECT COUNT(*) FROM bdc_test_scoring_marks WHERE round_id=:r AND (mark_type<>'blank' OR weighted_score>0)");$marks->execute(['r'=>$rid]);if((int)$marks->fetchColumn()>0)throw new RuntimeException('The YES tier cannot be changed after judging has started.');
   if($intent==='unlock')$pdo->prepare('UPDATE bdc_test_scoring_rounds SET tier_manual_override=0 WHERE id=:r')->execute(['r'=>$rid]);
   else{$yes=(int)($_POST['special_yes_count']??0);if(!in_array($yes,[5,10,15],true))throw new RuntimeException('Select 5, 10 or 15 YES per judge.');$pdo->prepare('UPDATE bdc_test_scoring_rounds SET yes_count=:y,callback_count=:y,tier_manual_override=1,yes_weight=10.00,alt1_weight=4.50,alt2_weight=4.30,alt3_weight=4.20 WHERE id=:r')->execute(['y'=>$yes,'r'=>$rid]);}
   header('Location: '.testWorkspaceUrl($testMode,$rid,['special_settings'=>1]),true,303);exit;
  }
 }
 if($action==='create_round'&&SpecialCategoryService::isSpecial((string)($_POST['division']??''))){
  $eventId=(int)($_POST['event_id']??0);$name=trim((string)($_POST['new_event_name']??''));$date=trim((string)($_POST['new_event_date']??''));$division=(string)$_POST['division'];$roundType=(string)($_POST['round_type']??'heats');
  if(!in_array($roundType,['heats','final'],true))throw new RuntimeException('Invalid round type.');
  if($eventId>0&&$name!=='')throw new RuntimeException('Select an existing event or create a new event, not both.');
  if($eventId<1){
   if($name==='')throw new RuntimeException('Select an existing event or enter a new event name.');
   if($date!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))throw new RuntimeException('Enter the event date as YYYY-MM-DD.');
   $base=strtolower(trim((string)preg_replace('/[^a-z0-9]+/i','-',$name),'-'))?:'event';$slug=$base;$n=2;$check=$pdo->prepare('SELECT COUNT(*) FROM bdc_test_events WHERE slug=:s');while(true){$check->execute(['s'=>$slug]);if(!(int)$check->fetchColumn())break;$slug=$base.'-'.$n++;}
   $pdo->prepare("INSERT INTO bdc_test_events(name,normalised_name,slug,event_date,status) VALUES(:n,:nn,:s,NULLIF(:d,''),'draft')")->execute(['n'=>$name,'nn'=>strtolower($name),'s'=>$slug,'d'=>$date]);$eventId=(int)$pdo->lastInsertId();
  }
  $s=$pdo->prepare("SELECT id FROM bdc_test_scoring_rounds WHERE event_id=:e AND division=:d AND round_type=:t AND status<>'archived' ORDER BY id DESC LIMIT 1");$s->execute(['e'=>$eventId,'d'=>$division,'t'=>$roundType]);$rid=(int)$s->fetchColumn();
  if($rid<1){$pdo->prepare("INSERT INTO bdc_test_scoring_rounds(event_id,round_type,division,yes_count,callback_count,yes_weight,alt1_weight,alt2_weight,alt3_weight,created_by) VALUES(:e,:t,:d,10,10,10.00,4.50,4.30,4.20,:u)")->execute(['e'=>$eventId,'t'=>$roundType,'d'=>$division,'u'=>$userId?:null]);$rid=(int)$pdo->lastInsertId();}
  header('Location: '.testWorkspaceUrl($testMode,$rid,['special_created'=>1]),true,303);exit;
 }
}
$_GET['legacy']=1;$_GET['test_mode']=$testMode;
require __DIR__.'/index.php';
?>
<script>window.BDC_SCORING_TEST_MODE={mode:<?=json_encode($testMode)?>,automaticEndpoint:<?=json_encode(url('admin/scoring-tests/automatic-inline.php'))?>,actionEndpoint:<?=json_encode(url('admin/scoring-tests/automatic-inline.php'))?>,dataEndpoint:<?=json_encode(url('admin/scoring-tests/mode-data.php'))?>};</script>
<script src="<?=e(url('public/js/scoring-tests-mode-v2412.js'))?>"></script>
