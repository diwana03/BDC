<?php
declare(strict_types=1);

require_once dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\SpecialCategoryService;

Auth::requireAdmin();
$pdo=Database::connection();
SpecialCategoryService::ensureSchema($pdo);
$userId=(int)(Auth::user()['id']??0);

function specialAudit(PDO $pdo,int $roundId,int $userId,string $action,array $details=[]):void{
    $stmt=$pdo->prepare('INSERT INTO bdc_scoring_audit(round_id,user_id,action,details_json) VALUES(:round,:user,:action,:details)');
    $stmt->execute(['round'=>$roundId,'user'=>$userId?:null,'action'=>$action,'details'=>json_encode($details,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
}
function specialEnsureDeskLink(PDO $pdo,int $eventId,string $category,int $userId):void{
    $stmt=$pdo->prepare('SELECT id FROM bdc_registration_desk_links WHERE event_id=:event AND division=:category LIMIT 1');
    $stmt->execute(['event'=>$eventId,'category'=>$category]);
    if($stmt->fetchColumn())return;
    $token=bin2hex(random_bytes(24));
    $pdo->prepare('INSERT INTO bdc_registration_desk_links(event_id,division,token_hash,token_hint,created_by) VALUES(:event,:category,:hash,:hint,:user)')->execute(['event'=>$eventId,'category'=>$category,'hash'=>hash('sha256',$token),'hint'=>substr($token,0,8),'user'=>$userId?:null]);
    $_SESSION['registration_desk_tokens'][(int)$pdo->lastInsertId()]=$token;
}
function specialFindCompetitor(PDO $pdo,string $term,string $role):?array{
    $bdc='';if(preg_match('/^(BDC-\d+)/i',$term,$match))$bdc=strtoupper($match[1]);
    $stmt=$pdo->prepare("SELECT id,bdc_id,exact_name,dance_role,current_division,status FROM bdc_competitors WHERE status<>'archived' AND dance_role IN(:role,'both') AND (bdc_id=:bdc OR id=:numeric OR LOWER(exact_name)=LOWER(:exact)) ORDER BY CASE WHEN dance_role=:preferred THEN 0 ELSE 1 END,id LIMIT 1");
    $stmt->execute(['role'=>$role,'bdc'=>$bdc!==''?$bdc:$term,'numeric'=>ctype_digit($term)?(int)$term:0,'exact'=>$term,'preferred'=>$role]);
    $competitor=$stmt->fetch();if($competitor)return $competitor;
    $stmt=$pdo->prepare("SELECT id,bdc_id,exact_name,dance_role,current_division,status FROM bdc_competitors WHERE status<>'archived' AND dance_role IN(:role,'both') AND exact_name LIKE :term ORDER BY exact_name,id LIMIT 2");
    $stmt->execute(['role'=>$role,'term'=>'%'.$term.'%']);$rows=$stmt->fetchAll();
    if(count($rows)===1)return $rows[0];if(count($rows)>1)throw new RuntimeException('Several competitors match this name. Select the correct BDC ID.');return null;
}
function specialCreateProvisional(PDO $pdo,string $name,string $role,string $reason):array{
    if($reason==='')throw new RuntimeException('Enter a reason before creating a provisional BDC competitor.');
    $normalised=strtolower(trim((string)preg_replace('/\s+/',' ',$name)));
    $same=$pdo->prepare('SELECT bdc_id,exact_name FROM bdc_competitors WHERE normalised_name=:name LIMIT 1');$same->execute(['name'=>$normalised]);
    if($existing=$same->fetch())throw new RuntimeException('A BDC record already exists: '.$existing['exact_name'].' ('.$existing['bdc_id'].').');
    $pdo->beginTransaction();try{
        $next=(int)$pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(bdc_id,5) AS UNSIGNED)),0)+1 FROM bdc_competitors WHERE bdc_id LIKE 'BDC-%'")->fetchColumn();
        $bdcId='BDC-'.str_pad((string)$next,6,'0',STR_PAD_LEFT);
        $stmt=$pdo->prepare("INSERT INTO bdc_competitors(bdc_id,exact_name,normalised_name,dance_role,current_division,status,is_historical,admin_notes) VALUES(:bdc,:name,:normalised,:role,'novice','pending',0,:notes)");
        $stmt->execute(['bdc'=>$bdcId,'name'=>$name,'normalised'=>$normalised,'role'=>$role,'notes'=>'Special-category provisional scoring entry: '.$reason]);
        $id=(int)$pdo->lastInsertId();$pdo->commit();return ['id'=>$id,'bdc_id'=>$bdcId,'exact_name'=>$name,'current_division'=>'novice','status'=>'pending'];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

$roundId=(int)($_GET['round_id']??$_POST['round_id']??0);$error='';
if($_SERVER['REQUEST_METHOD']==='POST'&&(string)($_POST['action']??'')==='create_special_round'){
 try{
  if(!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Invalid security token.');
  $category=(string)($_POST['special_category']??'');if(!SpecialCategoryService::isSpecial($category))throw new RuntimeException('Select a valid special category.');
  $scoringMode='manual';$roundType=(string)($_POST['round_type']??'heats');if(!in_array($roundType,['heats','final'],true))throw new RuntimeException('Select a valid first round.');
  $eventId=(int)($_POST['event_id']??0);$newName=trim((string)($_POST['new_event_name']??''));$newDate=trim((string)($_POST['new_event_date']??''));
  if($eventId>0&&$newName!=='')throw new RuntimeException('Select an existing event or create a new one, not both.');
  if($eventId<1){if($newName==='')throw new RuntimeException('Select an event or enter a new event name.');if($newDate!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$newDate))throw new RuntimeException('Enter the event date as YYYY-MM-DD.');$base=strtolower(trim((string)preg_replace('/[^a-z0-9]+/i','-',$newName),'-'))?:'event';$slug=$base;$n=2;$check=$pdo->prepare('SELECT COUNT(*) FROM bdc_events WHERE slug=:slug');while(true){$check->execute(['slug'=>$slug]);if(!(int)$check->fetchColumn())break;$slug=$base.'-'.$n++;}$pdo->prepare("INSERT INTO bdc_events(name,normalised_name,slug,event_date,status) VALUES(:name,:normalised,:slug,NULLIF(:date,''),'draft')")->execute(['name'=>$newName,'normalised'=>strtolower($newName),'slug'=>$slug,'date'=>$newDate]);$eventId=(int)$pdo->lastInsertId();}
  $existing=$pdo->prepare("SELECT id FROM bdc_scoring_rounds WHERE event_id=:event AND division=:category AND round_type=:round_type AND scoring_mode=:mode AND status<>'archived' ORDER BY id DESC LIMIT 1");$existing->execute(['event'=>$eventId,'category'=>$category,'round_type'=>$roundType,'mode'=>$scoringMode]);$roundId=(int)$existing->fetchColumn();
  if($roundId<1){$stmt=$pdo->prepare("INSERT INTO bdc_scoring_rounds(event_id,round_type,scoring_mode,division,yes_count,callback_count,yes_weight,alt1_weight,alt2_weight,alt3_weight,created_by) VALUES(:event,:round_type,:mode,:category,10,10,10.00,4.50,4.30,4.20,:user)");$stmt->execute(['event'=>$eventId,'round_type'=>$roundType,'mode'=>$scoringMode,'category'=>$category,'user'=>$userId?:null]);$roundId=(int)$pdo->lastInsertId();specialAudit($pdo,$roundId,$userId,'special_round_created',['category'=>$category,'fixed_points'=>SpecialCategoryService::schedule($category)]);}
  specialEnsureDeskLink($pdo,$eventId,$category,$userId);header('Location: ?mode=special&round_id='.$roundId);exit;
 }catch(Throwable $e){$error=$e->getMessage();}
}

if($roundId>0){
 $roundStmt=$pdo->prepare('SELECT r.*,e.name event_name FROM bdc_scoring_rounds r JOIN bdc_events e ON e.id=r.event_id WHERE r.id=:round LIMIT 1');$roundStmt->execute(['round'=>$roundId]);$specialRound=$roundStmt->fetch();
 if(!$specialRound||!SpecialCategoryService::isSpecial((string)$specialRound['division'])){http_response_code(404);exit('Special-category scoring round not found.');}
 if($_SERVER['REQUEST_METHOD']==='POST'&&(string)($_POST['action']??'')==='add_entry'){
  try{if(!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Invalid security token.');$role=(string)($_POST['dance_role']??'');$bib=(int)($_POST['bib_number']??0);$term=trim((string)($_POST['competitor_search']??''));$entryMode=(string)($_POST['entry_mode']??'existing');$reason=trim((string)($_POST['override_reason']??''));if(!in_array($role,['leader','follower'],true)||$bib<1||$term==='')throw new RuntimeException('Choose role, bib and competitor.');$dup=$pdo->prepare("SELECT display_name FROM bdc_scoring_entries WHERE round_id=:round AND dance_role=:role AND bib_number=:bib AND entry_status='active' LIMIT 1");$dup->execute(['round'=>$roundId,'role'=>$role,'bib'=>$bib]);if($taken=$dup->fetchColumn())throw new RuntimeException('Bib '.$bib.' is already assigned to '.$taken.'.');$competitor=specialFindCompetitor($pdo,$term,$role);if($competitor){if($entryMode==='create')throw new RuntimeException('This dancer already has a BDC record. Use '.$competitor['bdc_id'].'.');}else{if($entryMode!=='create')throw new RuntimeException('Competitor not found. Use the provisional option only for a genuinely new BDC competitor.');$competitor=specialCreateProvisional($pdo,$term,$role,$reason);}$pdo->prepare("INSERT INTO bdc_scoring_entries(round_id,competitor_id,dance_role,bib_number,display_name) VALUES(:round,:competitor,:role,:bib,:name) ON DUPLICATE KEY UPDATE bib_number=VALUES(bib_number),display_name=VALUES(display_name),entry_status='active'")->execute(['round'=>$roundId,'competitor'=>$competitor['id'],'role'=>$role,'bib'=>$bib,'name'=>$competitor['exact_name']]);specialAudit($pdo,$roundId,$userId,'special_entry_added',['category'=>$specialRound['division'],'competitor_id'=>$competitor['id'],'bdc_id'=>$competitor['bdc_id'],'role'=>$role,'bib'=>$bib,'provisional'=>$entryMode==='create']);header('Location: ?mode=special&round_id='.$roundId.'&special_notice='.rawurlencode($competitor['exact_name'].' added to '.SpecialCategoryService::label((string)$specialRound['division']).'.'));exit;}catch(Throwable $e){header('Location: ?mode=special&round_id='.$roundId.'&special_error='.rawurlencode($e->getMessage()));exit;}
 }
 $all=$pdo->query("SELECT bdc_id,exact_name,dance_role,current_division FROM bdc_competitors WHERE status<>'archived' ORDER BY exact_name LIMIT 1500")->fetchAll();$options='';foreach($all as $row){$options.='<option value="'.e((string)$row['bdc_id']).'">'.e($row['exact_name'].' · '.ucfirst((string)$row['dance_role']).' · '.ucwords(str_replace('_',' ',(string)$row['current_division']))).'</option>';}
 ob_start();require __DIR__.'/core.php';$html=(string)ob_get_clean();$label=SpecialCategoryService::label((string)$specialRound['division']);
 $html=str_replace(['Bachata_rising','Bachata_open','Bachata_invitational','BACHATA_RISING','BACHATA_OPEN','BACHATA_INVITATIONAL','href="?mode=manual"','publish.php?round_id=','registration-desk/?token=','<datalist id="finalCompetitorSuggestions"></datalist>'],['BDC Rising Star','BDC Open','Bachata Invitational','BACHATA RISING','BACHATA OPEN','BACHATA INVITATIONAL','href="?mode=special"','special-publish.php?round_id=','registration-desk/special.php?token=','<datalist id="finalCompetitorSuggestions">'.$options.'</datalist>'],$html);
 $schedule=[];foreach(SpecialCategoryService::schedule((string)$specialRound['division']) as $rank=>$points)$schedule[]=$rank.'='.number_format((float)$points,0).' pts';$banner='<div class="alert alert-info"><strong>'.$label.' fixed points:</strong> '.e(implode(' · ',$schedule)).'. Participant-count point tiers do not apply. Special-category points are recorded under each dancer’s role-specific BDC Novice, Intermediate or Advanced progression bucket.</div>';if(isset($_GET['special_notice']))$banner='<div class="alert alert-success">'.e((string)$_GET['special_notice']).'</div>'.$banner;if(isset($_GET['special_error']))$banner='<div class="alert alert-danger">'.e((string)$_GET['special_error']).'</div>'.$banner;$needle='<div class="container-fluid py-4" style="max-width:1600px">';$html=str_replace($needle,$needle.$banner,$html);echo $html;exit;
}

$events=$pdo->query('SELECT id,name,event_date FROM bdc_events ORDER BY event_date DESC,name')->fetchAll();$rounds=$pdo->query("SELECT r.id,r.event_id,r.division,r.round_type,r.scoring_mode,r.status,r.updated_at,e.name event_name,e.event_date FROM bdc_scoring_rounds r JOIN bdc_events e ON e.id=r.event_id WHERE r.division IN ('bachata_rising','bachata_open','bachata_invitational') ORDER BY r.updated_at DESC,r.id DESC")->fetchAll();$csrf=Csrf::token();$categories=SpecialCategoryService::categories();
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Special Category Scoring | BDC Admin</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="../../public/css/scoring-premium.css?v=274" rel="stylesheet"><style>body{background:#f4f6f9}.special-shell{max-width:1200px}.category-card{border:1px solid #e1e5ea;border-radius:16px}.fixed-points{font-weight:700;color:#8a650d}</style></head><body><nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="../">BDC Admin</a><div class="d-flex gap-2"><a class="btn btn-warning btn-sm" href="https://bachatadancecouncil.com/">BDC Home</a><a class="btn btn-outline-light btn-sm" href="?">Scoring Modes</a></div></div></nav><main class="container special-shell py-5"><div class="mb-4"><div class="text-uppercase text-primary fw-bold small">BDC Special Categories</div><h1 class="h2">Special Category Scoring</h1><p class="text-muted">Rising, Open and Invitational use fixed points. Normal participant-count point tiers do not apply.</p></div><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><div class="row g-3 mb-4"><?php foreach($categories as $key=>$label):$schedule=[];foreach(SpecialCategoryService::schedule($key) as $rank=>$points)$schedule[]=$rank.'='.number_format((float)$points,0);?><div class="col-md-4"><div class="card category-card h-100"><div class="card-body"><h2 class="h5"><?=e($label)?></h2><div class="fixed-points"><?=e(implode(' · ',$schedule))?> points</div><p class="small text-muted mt-2 mb-0"><?=e(SpecialCategoryService::entryEligibility($key)['reason'])?></p></div></div></div><?php endforeach;?></div><div class="card shadow-sm mb-4"><div class="card-body"><h2 class="h5">Create