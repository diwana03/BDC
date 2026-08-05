<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use App\Core\Database;
use App\Core\Csrf;
use App\Services\SchemaUpdater;

$pdo=Database::connection();
$token=trim((string)($_GET['token']??$_POST['token']??''));
if($token===''){http_response_code(403);exit('Registration Desk link is missing.');}
$hash=hash('sha256',$token);
$stmt=$pdo->prepare("SELECT l.*,e.name event_name,e.event_date FROM bdc_registration_desk_links l JOIN bdc_events e ON e.id=l.event_id WHERE l.token_hash=:hash AND l.is_enabled=1 AND (l.expires_at IS NULL OR l.expires_at>NOW()) LIMIT 1");
$stmt->execute(['hash'=>$hash]);$desk=$stmt->fetch();
if(!$desk){http_response_code(403);exit('This Registration Desk link is invalid, disabled or expired.');}

$requestedRoundId=(int)($_GET['round_id']??$_POST['round_id']??0);
if($requestedRoundId>0){
 $roundStmt=$pdo->prepare("SELECT * FROM bdc_scoring_rounds WHERE id=:round AND event_id=:event AND division=:division AND status<>'archived' LIMIT 1");
 $roundStmt->execute(['round'=>$requestedRoundId,'event'=>$desk['event_id'],'division'=>$desk['division']]);
}else{
 $roundStmt=$pdo->prepare("SELECT * FROM bdc_scoring_rounds WHERE event_id=:event AND division=:division AND status NOT IN ('archived','completed') ORDER BY id DESC LIMIT 1");
 $roundStmt->execute(['event'=>$desk['event_id'],'division'=>$desk['division']]);
}
$round=$roundStmt->fetch();
if(!$round){http_response_code(404);exit('The selected scoring round is not available for this Registration Desk link.');}
$roundId=(int)$round['id'];
$message='';$error='';

function logDesk(PDO $pdo,array $desk,string $action,?int $competitorId,?string $name,array $details=[]):void{
 $stmt=$pdo->prepare("INSERT INTO bdc_registration_desk_activity(desk_link_id,event_id,division,action,competitor_id,competitor_name,details) VALUES(:link,:event,:division,:action,:competitor,:name,:details)");
 $stmt->execute(['link'=>$desk['id'],'event'=>$desk['event_id'],'division'=>$desk['division'],'action'=>$action,'competitor'=>$competitorId,'name'=>$name,'details'=>$details?json_encode($details,JSON_UNESCAPED_SLASHES):null]);
}

if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Invalid security token.');
  $action=(string)($_POST['action']??'');
  if($action==='add_existing'){
   $competitorId=(int)($_POST['competitor_id']??0);$role=(string)($_POST['role']??'');$bib=(int)($_POST['bib']??0);
   if(!in_array($role,['leader','follower'],true)||$bib<1)throw new RuntimeException('Select role and enter a valid bib.');
   $c=$pdo->prepare("SELECT id,exact_name FROM bdc_competitors WHERE id=:id AND status='active'");$c->execute(['id'=>$competitorId]);$competitor=$c->fetch();
   if(!$competitor)throw new RuntimeException('Competitor not found.');
   $dup=$pdo->prepare("SELECT COUNT(*) FROM bdc_scoring_entries WHERE round_id=:round AND dance_role=:role AND bib_number=:bib AND entry_status='active'");$dup->execute(['round'=>$roundId,'role'=>$role,'bib'=>$bib]);if((int)$dup->fetchColumn()>0)throw new RuntimeException('Bib '.$bib.' is already used for this role.');
   $insert=$pdo->prepare("INSERT INTO bdc_scoring_entries(round_id,competitor_id,dance_role,bib_number,display_name,entry_status,desk_checked_in,desk_ready,desk_updated_at) VALUES(:round,:competitor,:role,:bib,:name,'active',1,1,NOW()) ON DUPLICATE KEY UPDATE bib_number=VALUES(bib_number),display_name=VALUES(display_name),entry_status='active',desk_checked_in=1,desk_ready=1,desk_updated_at=NOW()");
   $insert->execute(['round'=>$roundId,'competitor'=>$competitorId,'role'=>$role,'bib'=>$bib,'name'=>$competitor['exact_name']]);
   logDesk($pdo,$desk,'competitor_added',$competitorId,$competitor['exact_name'],['role'=>$role,'bib'=>$bib]);$message='Competitor added and synced to Heats.';
  }elseif($action==='update_entry'){
   $entryId=(int)($_POST['entry_id']??0);$bib=(int)($_POST['bib']??0);$ready=isset($_POST['ready'])?1:0;$checked=isset($_POST['checked'])?1:0;
   $entryStmt=$pdo->prepare("SELECT * FROM bdc_scoring_entries WHERE id=:id AND round_id=:round");$entryStmt->execute(['id'=>$entryId,'round'=>$roundId]);$entry=$entryStmt->fetch();
   if(!$entry)throw new RuntimeException('Entry not found.');
   if($bib<1)throw new RuntimeException('Bib must be at least 1.');
   $dup=$pdo->prepare("SELECT COUNT(*) FROM bdc_scoring_entries WHERE round_id=:round AND dance_role=:role AND bib_number=:bib AND id<>:id AND entry_status='active'");$dup->execute(['round'=>$roundId,'role'=>$entry['dance_role'],'bib'=>$bib,'id'=>$entryId]);if((int)$dup->fetchColumn()>0)throw new RuntimeException('Bib '.$bib.' is already used for this role.');
   $pdo->prepare("UPDATE bdc_scoring_entries SET bib_number=:bib,desk_checked_in=:checked,desk_ready=:ready,desk_updated_at=NOW() WHERE id=:id AND round_id=:round")->execute(['bib'=>$bib,'checked'=>$checked,'ready'=>$ready,'id'=>$entryId,'round'=>$roundId]);
   logDesk($pdo,$desk,'entry_updated',(int)$entry['competitor_id'],$entry['display_name'],['bib'=>$bib,'checked'=>$checked,'ready'=>$ready]);$message='Entry updated and synced live.';
  }elseif($action==='withdraw'){
   $entryId=(int)($_POST['entry_id']??0);
   $entryStmt=$pdo->prepare("SELECT * FROM bdc_scoring_entries WHERE id=:id AND round_id=:round");$entryStmt->execute(['id'=>$entryId,'round'=>$roundId]);$entry=$entryStmt->fetch();
   if(!$entry)throw new RuntimeException('Entry not found.');
   $pdo->prepare("UPDATE bdc_scoring_entries SET entry_status='withdrawn',desk_ready=0,desk_updated_at=NOW() WHERE id=:id")->execute(['id'=>$entryId]);
   logDesk($pdo,$desk,'entry_withdrawn',(int)$entry['competitor_id'],$entry['display_name']);$message='Competitor withdrawn.';
  }
 }catch(Throwable $e){$error=$e->getMessage();}
}

$search=trim((string)($_GET['q']??''));
$matches=[];
if($search!==''){
 $q=$pdo->prepare("SELECT id,bdc_id,exact_name,country,dance_role FROM bdc_competitors WHERE status='active' AND (exact_name LIKE :term OR bdc_id LIKE :term) ORDER BY exact_name LIMIT 30");
 $q->execute(['term'=>'%'.$search.'%']);$matches=$q->fetchAll();
}
$entriesStmt=$pdo->prepare("SELECT * FROM bdc_scoring_entries WHERE round_id=:round ORDER BY dance_role,bib_number,display_name");$entriesStmt->execute(['round'=>$roundId]);$entries=$entriesStmt->fetchAll();
$csrf=Csrf::token();
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>BDC Registration Desk</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:#f3f4f6}.card{border:0;border-radius:14px}.desk-head{background:#111827;color:#fff}.ready{background:#dcfce7}.not-ready{background:#fef3c7}</style></head><body>
<header class="desk-head py-3"><div class="container"><h1 class="h4 mb-1">BDC Registration Desk</h1><div><?=e($desk['event_name'])?> · <?=e(ucfirst($desk['division']))?> · <?=e(ucfirst($round['round_type']))?></div></div></header>
<main class="container py-4"><?php if($message):?><div class="alert alert-success"><?=e($message)?></div><?php endif;?><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
<div class="card shadow-sm mb-4"><div class="card-body"><h2 class="h5">Search Existing BDC Competitor</h2><form method="get" class="row g-2"><input type="hidden" name="token" value="<?=e($token)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><div class="col-md-10"><input class="form-control" name="q" value="<?=e($search)?>" placeholder="Search exact name or BDC ID"></div><div class="col-md-2"><button class="btn btn-primary w-100">Search</button></div></form>
<?php if($matches):?><div class="table-responsive mt-3"><table class="table align-middle"><thead><tr><th>BDC ID</th><th>Name</th><th>Country</th><th>Add</th></tr></thead><tbody><?php foreach($matches as $match):?><tr><td><?=e($match['bdc_id'])?></td><td><?=e($match['exact_name'])?></td><td><?=e((string)$match['country'])?></td><td><form method="post" class="row g-2"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="token" value="<?=e($token)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="action" value="add_existing"><input type="hidden" name="competitor_id" value="<?=$match['id']?>"><div class="col"><select class="form-select" name="role"><option value="leader">Leader</option><option value="follower">Follower</option></select></div><div class="col"><input class="form-control" type="number" min="1" name="bib" placeholder="Bib" required></div><div class="col"><button class="btn btn-success">Add &amp; Ready</button></div></form></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></div></div>
<div class="card shadow-sm"><div class="card-body"><div class="d-flex justify-content-between align-items-center"><h2 class="h5">Live Registration List</h2><span class="badge text-bg-primary">Auto refresh 5s</span></div><div id="entryList"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Role</th><th>Bib</th><th>Name</th><th>Checked In</th><th>Ready</th><th>Actions</th></tr></thead><tbody><?php foreach($entries as $entry):?><tr class="<?=$entry['desk_ready']?'ready':'not-ready'?>"><td><?=e(ucfirst($entry['dance_role']))?></td><td colspan="5"><form method="post" class="row g-2 align-items-center"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="token" value="<?=e($token)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="entry_id" value="<?=$entry['id']?>"><div class="col-md-1"><input class="form-control" type="number" min="1" name="bib" value="<?=$entry['bib_number']?>" required></div><div class="col-md-4"><strong><?=e($entry['display_name'])?></strong></div><div class="col-md-2"><label><input type="checkbox" name="checked" <?=$entry['desk_checked_in']?'checked':''?>> Checked In</label></div><div class="col-md-2"><label><input type="checkbox" name="ready" <?=$entry['desk_ready']?'checked':''?>> Ready</label></div><div class="col-md-3 d-flex gap-2"><button class="btn btn-sm btn-primary" name="action" value="update_entry">Save</button><button class="btn btn-sm btn-outline-danger" name="action" value="withdraw" formnovalidate>Withdraw</button></div></form></td></tr><?php endforeach;?></tbody></table></div></div></div></div></main>
<script>setTimeout(()=>{if(!document.activeElement||!['INPUT','SELECT'].includes(document.activeElement.tagName))location.reload();},5000);</script></body></html>
