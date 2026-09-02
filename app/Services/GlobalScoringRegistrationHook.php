<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Auth;use App\Core\Csrf;use App\Core\Database;use Throwable;
final class GlobalScoringRegistrationHook
{
 public static function handle(string $method,string $path,string $testMode=''):void
 {
  if($method!=='POST'||(string)($_POST['action']??'')!=='add_entry')return;
  $isDesk=preg_match('#/registration-desk(?:/(?:index|special)\.php)?/?$#',$path)===1;
  $isTest=preg_match('#/admin/scoring-tests(?:/index\.php)?/?$#',$path)===1;
  $isManual=preg_match('#/admin/scoring(?:/index\.php|/core\.php)?/?$#',$path)===1;
  $isAutomatic=preg_match('#/admin/scoring/(?:automatic-round|automatic-setup-action)\.php/?$#',$path)===1;
  $isDiscipline=preg_match('#/admin/scoring/discipline-actions\.php/?$#',$path)===1;
  $create=(string)($_POST['entry_mode']??'existing')==='create';
  if(!$isDesk&&!$isTest&&!$isManual&&!$isAutomatic&&!$isDiscipline)return;
  if(!$isDesk)Auth::requireAdmin();
  if(!Csrf::verify($_POST['_csrf']??null)){http_response_code(419);exit('Invalid security token.');}
  $pdo=Database::connection();$roundId=(int)($_POST['round_id']??0);$role=(string)($_POST['dance_role']??'');$name=trim((string)($_POST['competitor_search']??''));
  $rawBib=trim((string)($isDesk?($_POST['bib']??''):($_POST['bib_number']??'')));$bib=$rawBib===''?null:(int)$rawBib;
  if($roundId<1||!in_array($role,['leader','follower'],true)||$name===''||($bib!==null&&$bib<1)){http_response_code(400);exit('Choose role and competitor name. Bib may be left blank and assigned later.');}
  $roundTable=$isTest?'bdc_test_scoring_rounds':'bdc_scoring_rounds';$entryTable=$isTest?'bdc_test_scoring_entries':'bdc_scoring_entries';
  $rs=$pdo->prepare("SELECT event_id,division,dance_style FROM {$roundTable} WHERE id=:id LIMIT 1");$rs->execute(['id'=>$roundId]);$round=$rs->fetch();if(!$round){http_response_code(404);exit('Scoring round not found.');}
  if($isDesk){$token=trim((string)($_POST['token']??''));$ls=$pdo->prepare("SELECT id,event_id,division FROM bdc_registration_desk_links WHERE token_hash=:hash AND is_enabled=1 AND (expires_at IS NULL OR expires_at>NOW()) LIMIT 1");$ls->execute(['hash'=>hash('sha256',$token)]);$link=$ls->fetch();if(!$link||(int)$link['event_id']!==(int)$round['event_id']||(string)$link['division']!==(string)$round['division']){http_response_code(403);exit('Registration Desk link is invalid for this round.');}}
  if($bib!==null){$bs=$pdo->prepare("SELECT display_name FROM {$entryTable} WHERE round_id=:round AND dance_role=:role AND bib_number=:bib AND entry_status='active' LIMIT 1");$bs->execute(['round'=>$roundId,'role'=>$role,'bib'=>$bib]);if($taken=$bs->fetchColumn()){http_response_code(409);exit('Bib '.$bib.' is already assigned to '.$taken.'.');}}
  try{
   $danceStyle=JackJillCompetitorEligibilityService::dance((string)($round['dance_style']??''));
   if($create)throw new \RuntimeException('Scoring cannot create a council identity. Create and approve the '.JackJillCompetitorEligibilityService::council($danceStyle).' competitor profile first, then add it from the roster search.');
   $competitor=JackJillCompetitorEligibilityService::requireEligible($pdo,$danceStyle,$name,$role);
   $elig=DivisionProgressionService::eligibilityFromApprovedHistory($pdo,(int)$competitor['id'],$role,$danceStyle,(string)$round['division']);
   if(!$elig['eligible'])throw new \RuntimeException('Cannot add '.$competitor['exact_name'].': '.$elig['reason']);
   if($isTest)CompetitorIdentityService::mirrorOfficialToTest($pdo,$competitor);
   $entry=$pdo->prepare("INSERT INTO {$entryTable}(round_id,competitor_id,dance_role,bib_number,display_name,entry_status) VALUES(:round,:competitor,:role,:bib,:name,'active') ON DUPLICATE KEY UPDATE bib_number=VALUES(bib_number),display_name=VALUES(display_name),entry_status='active'");$entry->execute(['round'=>$roundId,'competitor'=>(int)$competitor['id'],'role'=>$role,'bib'=>$bib,'name'=>(string)$competitor['exact_name']]);
   if($isDesk){$pdo->prepare("UPDATE bdc_scoring_entries SET desk_checked_in=1,desk_updated_at=NOW() WHERE round_id=:round AND competitor_id=:competitor AND dance_role=:role")->execute(['round'=>$roundId,'competitor'=>$competitor['id'],'role'=>$role]);header('Location: '.url('registration-desk/?token='.rawurlencode((string)$_POST['token']).'&round_id='.$roundId),true,303);exit;}
   $auditTable=$isTest?'bdc_test_scoring_audit':'bdc_scoring_audit';$audit=$pdo->prepare("INSERT INTO {$auditTable}(round_id,user_id,action,details_json) VALUES(:round,:user,'council_competitor_added',:details)");$audit->execute(['round'=>$roundId,'user'=>(int)(Auth::user()['id']??0)?:null,'details'=>json_encode(['competitor_id'=>(int)$competitor['id'],'council'=>JackJillCompetitorEligibilityService::council($danceStyle),'identity_code'=>(string)$competitor['identity_code'],'role'=>$role,'bib'=>$bib,'bib_status'=>$bib===null?'unassigned':'assigned','entered_division'=>(string)$round['division'],'test_only'=>$isTest,'permanent_division_changed'=>false],JSON_UNESCAPED_SLASHES)]);
   if($isTest){$mode=in_array($testMode,['manual','automated'],true)?$testMode:'manual';$target='admin/scoring-tests/index.php?legacy=1&test_mode='.$mode.'&round_id='.$roundId.'&competitor_added=1';}elseif($isAutomatic)$target='admin/scoring/automatic-round.php?round_id='.$roundId.'&competitor_added=1';else{$mode=(string)($_GET['mode']??$_POST['mode']??'manual');$target='admin/scoring/index.php?mode='.rawurlencode(in_array($mode,['manual','automated'],true)?$mode:'manual').'&round_id='.$roundId.'&competitor_added=1';}
   header('Location: '.url($target),true,303);exit;
  }catch(Throwable $e){http_response_code(400);exit($e->getMessage());}
 }
}
