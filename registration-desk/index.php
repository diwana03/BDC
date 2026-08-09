<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';

use App\Core\Csrf;
use App\Core\Database;
use App\Services\DivisionProgressionService;

$pdo=Database::connection();
$token=trim((string)($_GET['token']??$_POST['token']??''));
if($token===''){http_response_code(403);exit('Registration Desk link is missing.');}
$stmt=$pdo->prepare("SELECT l.*,e.name event_name,e.event_date FROM bdc_registration_desk_links l JOIN bdc_events e ON e.id=l.event_id WHERE l.token_hash=:hash AND l.is_enabled=1 AND (l.expires_at IS NULL OR l.expires_at>NOW()) LIMIT 1");
$stmt->execute(['hash'=>hash('sha256',$token)]);$desk=$stmt->fetch();
if(!$desk){http_response_code(403);exit('This Registration Desk link is invalid, disabled or expired.');}
$requestedRoundId=(int)($_GET['round_id']??$_POST['round_id']??0);
$roundStmt=$pdo->prepare($requestedRoundId>0
 ?"SELECT * FROM bdc_scoring_rounds WHERE id=:round AND event_id=:event AND division=:division AND status<>'archived' LIMIT 1"
 :"SELECT * FROM bdc_scoring_rounds WHERE event_id=:event AND division=:division AND status NOT IN('archived','completed') ORDER BY id DESC LIMIT 1");
$params=['event'=>$desk['event_id'],'division'=>$desk['division']];if($requestedRoundId>0)$params['round']=$requestedRoundId;
$roundStmt->execute($params);$round=$roundStmt->fetch();
if(!$round){http_response_code(404);exit('The selected scoring round is not available for this Registration Desk link.');}
$roundId=(int)$round['id'];

function logDesk(PDO $pdo,array $desk,string $action,array $entry,array $details=[]):void{
 $stmt=$pdo->prepare("INSERT INTO bdc_registration_desk_activity(desk_link_id,event_id,division,action,competitor_id,competitor_name,details) VALUES(:link,:event,:division,:action,:competitor,:name,:details)");
 $stmt->execute(['link'=>$desk['id'],'event'=>$desk['event_id'],'division'=>$desk['division'],'action'=>$action,'competitor'=>$entry['competitor_id']??null,'name'=>$entry['display_name']??null,'details'=>json_encode($details,JSON_UNESCAPED_SLASHES)]);
}
function deskEntries(PDO $pdo,int $roundId):array{
 $stmt=$pdo->prepare("SELECT se.id,se.competitor_id,se.dance_role,se.bib_number,se.display_name,se.entry_status,se.desk_checked_in,se.desk_ready,se.desk_updated_at,se.updated_at,c.bdc_id FROM bdc_scoring_entries se LEFT JOIN bdc_competitors c ON c.id=se.competitor_id WHERE se.round_id=:round ORDER BY se.dance_role,se.bib_number IS NULL,se.bib_number,se.display_name");
 $stmt->execute(['round'=>$roundId]);return $stmt->fetchAll();
}
function respond(array $payload,int $status=200):never{
 http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($payload,JSON_UNESCAPED_SLASHES);exit;
}
function deskCompetitorEligibility(PDO $pdo,array $competitor,string $role,string $division):array{
 $pointsStmt=$pdo->prepare("SELECT
  COALESCE(SUM(CASE WHEN division='novice' THEN points ELSE 0 END),0) novice_points,
  COALESCE(SUM(CASE WHEN division='intermediate' THEN points ELSE 0 END),0) intermediate_points,
  COALESCE(SUM(CASE WHEN division='advanced' THEN points ELSE 0 END),0) advanced_points
  FROM bdc_point_transactions WHERE competitor_id=:competitor AND dance_role IN(:role,'both')");
 $pointsStmt->execute(['competitor'=>$competitor['id'],'role'=>$role]);$points=$pointsStmt->fetch()?:[];
 $historyStmt=$pdo->prepare("SELECT
  MAX(CASE WHEN division='intermediate' THEN 1 ELSE 0 END) competed_intermediate,
  MAX(CASE WHEN division='advanced' THEN 1 ELSE 0 END) competed_advanced,
  MAX(CASE WHEN division='all_star' THEN 1 ELSE 0 END) competed_all_star
  FROM (
   SELECT division FROM bdc_participant_results WHERE competitor_id=:participant AND dance_role IN(:participant_role,'both')
   UNION ALL
   SELECT division FROM bdc_point_transactions WHERE competitor_id=:transaction AND dance_role IN(:transaction_role,'both')
  ) history");
 $historyStmt->execute(['participant'=>$competitor['id'],'participant_role'=>$role,'transaction'=>$competitor['id'],'transaction_role'=>$role]);$history=$historyStmt->fetch()?:[];
 return DivisionProgressionService::eligibilityFor($division,(float)($points['novice_points']??0),(float)($points['intermediate_points']??0),(float)($points['advanced_points']??0),(string)($competitor['current_division']??'unknown'),!empty($history['competed_intermediate']),!empty($history['competed_advanced']),!empty($history['competed_all_star']));
}
function findDeskCompetitor(PDO $pdo,string $term,string $role):?array{
 $bdc='';if(preg_match('/^(BDC-\d+)/i',$term,$match))$bdc=strtoupper($match[1]);
 $stmt=$pdo->prepare("SELECT id,bdc_id,exact_name,dance_role,current_division,status FROM bdc_competitors WHERE status<>'archived' AND dance_role IN(:role,'both') AND (bdc_id=:bdc OR id=:numeric OR LOWER(exact_name)=LOWER(:exact)) ORDER BY CASE WHEN dance_role=:preferred THEN 0 ELSE 1 END,id LIMIT 1");
 $stmt->execute(['role'=>$role,'bdc'=>$bdc!==''?$bdc:$term,'numeric'=>ctype_digit($term)?(int)$term:0,'exact'=>$term,'preferred'=>$role]);return $stmt->fetch()?:null;
}

$isAjax=str_contains((string)($_SERVER['HTTP_ACCEPT']??''),'application/json')||($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='XMLHttpRequest';
if(isset($_GET['competitor_search'])){
 $term=trim((string)$_GET['competitor_search']);$role=(string)($_GET['role']??'');
 if(!in_array($role,['leader','follower'],true)||mb_strlen($term)<2)respond(['ok'=>true,'competitors'=>[]]);
 $stmt=$pdo->prepare("SELECT id,bdc_id,exact_name,dance_role,current_division,status FROM bdc_competitors WHERE status<>'archived' AND dance_role IN(:role,'both') AND (bdc_id LIKE :prefix OR exact_name LIKE :contains) ORDER BY CASE WHEN dance_role=:preferred THEN 0 ELSE 1 END,exact_name LIMIT 20");
 $stmt->execute(['role'=>$role,'prefix'=>$term.'%','contains'=>'%'.$term.'%','preferred'=>$role]);$matches=[];
 foreach($stmt->fetchAll() as $competitor){$eligibility=deskCompetitorEligibility($pdo,$competitor,$role,(string)$round['division']);$matches[]=['bdc_id'=>$competitor['bdc_id'],'name'=>$competitor['exact_name'],'role'=>$competitor['dance_role'],'eligible'=>$eligibility['eligible'],'reason'=>$eligibility['reason']];}
 respond(['ok'=>true,'competitors'=>$matches]);
}
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Invalid security token. Refresh the desk and try again.');
  $action=(string)($_POST['action']??'');$entryId=(int)($_POST['entry_id']??0);
  if($action==='add_entry'){
   $role=(string)($_POST['dance_role']??'');$term=trim((string)($_POST['competitor_search']??''));$bib=(int)($_POST['bib']??0);$create=($_POST['entry_mode']??'existing')==='create';$reason=trim((string)($_POST['override_reason']??''));
   if(!in_array($role,['leader','follower'],true)||$term===''||$bib<1)throw new RuntimeException('Choose a role, competitor and valid bib number.');
   $dup=$pdo->prepare("SELECT display_name FROM bdc_scoring_entries WHERE round_id=:round AND dance_role=:role AND bib_number=:bib AND entry_status='active' LIMIT 1");$dup->execute(['round'=>$roundId,'role'=>$role,'bib'=>$bib]);if($name=$dup->fetchColumn())throw new RuntimeException('Bib '.$bib.' is already assigned to '.$name.' on the '.ucfirst($role).' side.');
   $competitor=findDeskCompetitor($pdo,$term,$role);
   if($competitor){
    if($create)throw new RuntimeException('This dancer already has a BDC record. Select '.$competitor['bdc_id'].' instead of creating a provisional record.');
    $eligibility=deskCompetitorEligibility($pdo,$competitor,$role,(string)$round['division']);
    if(!$eligibility['eligible'])throw new RuntimeException('Cannot add '.$competitor['exact_name'].': '.$eligibility['reason'].' Known BDC competitors cannot bypass eligibility.');
   }else{
    if(!$create)throw new RuntimeException('Competitor not found. Use the provisional option only when the BDC record or points have not yet been imported.');
    if($reason==='')throw new RuntimeException('Enter a reason before adding a provisional competitor.');
    $normalised=strtolower(trim((string)preg_replace('/\s+/',' ',$term)));$existing=$pdo->prepare("SELECT bdc_id,exact_name FROM bdc_competitors WHERE normalised_name=:name LIMIT 1");$existing->execute(['name'=>$normalised]);if($same=$existing->fetch())throw new RuntimeException('A BDC record already exists: '.$same['exact_name'].' ('.$same['bdc_id'].').');
    $pdo->beginTransaction();
    try{$next=(int)$pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(bdc_id,5) AS UNSIGNED)),0)+1 FROM bdc_competitors WHERE bdc_id LIKE 'BDC-%'")->fetchColumn();$bdcId='BDC-'.str_pad((string)$next,6,'0',STR_PAD_LEFT);$insert=$pdo->prepare("INSERT INTO bdc_competitors(bdc_id,exact_name,normalised_name,dance_role,current_division,status,is_historical,admin_notes) VALUES(:bdc,:name,:normalised,:role,:division,'pending',0,:notes)");$insert->execute(['bdc'=>$bdcId,'name'=>$term,'normalised'=>$normalised,'role'=>$role,'division'=>$round['division'],'notes'=>'Registration Desk provisional entry: '.$reason]);$competitor=['id'=>(int)$pdo->lastInsertId(),'bdc_id'=>$bdcId,'exact_name'=>$term];$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
   }
   $pdo->prepare("INSERT INTO bdc_scoring_entries(round_id,competitor_id,dance_role,bib_number,display_name,desk_checked_in,desk_updated_at) VALUES(:round,:competitor,:role,:bib,:name,1,NOW()) ON DUPLICATE KEY UPDATE bib_number=VALUES(bib_number),display_name=VALUES(display_name),entry_status='active',desk_checked_in=1,desk_updated_at=NOW()")->execute(['round'=>$roundId,'competitor'=>$competitor['id'],'role'=>$role,'bib'=>$bib,'name'=>$competitor['exact_name']]);
   logDesk($pdo,$desk,'competitor_added',$competitor,['role'=>$role,'bib'=>$bib,'bdc_id'=>$competitor['bdc_id'],'provisional'=>$create,'override_reason'=>$create?$reason:null]);
  }else{
   $entryStmt=$pdo->prepare("SELECT * FROM bdc_scoring_entries WHERE id=:id AND round_id=:round");$entryStmt->execute(['id'=>$entryId,'round'=>$roundId]);$entry=$entryStmt->fetch();
   if(!$entry)throw new RuntimeException('Entry not found in this round.');
  if($action==='update_entry'){
   $bib=trim((string)($_POST['bib']??''));$bibNumber=$bib===''?null:(int)$bib;
   if($bibNumber!==null&&$bibNumber<1)throw new RuntimeException('Bib must be blank or at least 1.');
   if($bibNumber!==null){$dup=$pdo->prepare("SELECT display_name FROM bdc_scoring_entries WHERE round_id=:round AND dance_role=:role AND bib_number=:bib AND id<>:id AND entry_status='active' LIMIT 1");$dup->execute(['round'=>$roundId,'role'=>$entry['dance_role'],'bib'=>$bibNumber,'id'=>$entryId]);if($name=$dup->fetchColumn())throw new RuntimeException('Bib '.$bibNumber.' is already assigned to '.$name.' on the '.ucfirst($entry['dance_role']).' side.');}
   $checked=($_POST['checked']??'0')==='1'?1:0;$ready=($_POST['ready']??'0')==='1'?1:0;$status=(string)($_POST['status']??'active');
   if(!in_array($status,['active','withdrawn'],true))throw new RuntimeException('Invalid attendance status.');
   if($status==='withdrawn')$ready=0;
   $pdo->prepare("UPDATE bdc_scoring_entries SET bib_number=:bib,entry_status=:status,desk_checked_in=:checked,desk_ready=:ready,desk_updated_at=NOW() WHERE id=:id AND round_id=:round")->execute(['bib'=>$bibNumber,'status'=>$status,'checked'=>$checked,'ready'=>$ready,'id'=>$entryId,'round'=>$roundId]);
   logDesk($pdo,$desk,'entry_updated',$entry,['role'=>$entry['dance_role'],'bib'=>$bibNumber,'status'=>$status,'checked_in'=>$checked,'ready'=>$ready]);
  }elseif($action==='restore_entry'){
   $pdo->prepare("UPDATE bdc_scoring_entries SET entry_status='active',desk_updated_at=NOW() WHERE id=:id AND round_id=:round")->execute(['id'=>$entryId,'round'=>$roundId]);logDesk($pdo,$desk,'entry_restored',$entry);
  }else throw new RuntimeException('Invalid Registration Desk action.');
  }
  if($isAjax)respond(['ok'=>true,'entries'=>deskEntries($pdo,$roundId)]);
 }catch(Throwable $e){if($isAjax)respond(['ok'=>false,'error'=>$e->getMessage()],422);$error=$e->getMessage();}
}
if(isset($_GET['live'])){
 $since=trim((string)($_GET['since']??''));$entries=deskEntries($pdo,$roundId);$latest='';foreach($entries as $entry)$latest=max($latest,(string)($entry['desk_updated_at']?:$entry['updated_at']));
 respond(['ok'=>true,'changed'=>$since===''||$latest>$since,'latest'=>$latest,'entries'=>$since===''||$latest>$since?$entries:[]]);
}
$entries=deskEntries($pdo,$roundId);$csrf=Csrf::token();
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>BDC Registration Desk</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{background:#f3f4f6}.card{border:0;border-radius:14px}.desk-head{background:#111827;color:#fff}.entry-ready{background:#dcfce7}.entry-withdrawn{opacity:.55}.sticky-tools{position:sticky;top:0;z-index:10;background:#f3f4f6;padding:.5rem 0}</style></head><body>
<header class="desk-head py-3"><div class="container"><h1 class="h4 mb-1">BDC Registration Desk</h1><div><?=e($desk['event_name'])?> · <?=e(ucfirst($desk['division']))?> · <?=e(ucfirst($round['round_type']))?></div></div></header>
<main class="container py-3"><div id="notice"></div><div class="sticky-tools"><div class="card shadow-sm"><div class="card-body py-2 d-flex flex-wrap gap-2 align-items-center"><input id="deskSearch" class="form-control" style="max-width:360px" placeholder="Search registered name, BDC ID or bib"><span id="liveState" class="badge text-bg-success">Live</span><span id="summary" class="small text-muted"></span></div></div></div><div class="card shadow-sm mt-3"><div class="card-body"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h2 class="h5 mb-1">Add competitor</h2><div class="small text-muted">Search the BDC database first. Eligibility is checked by division and role. Bib is optional and can be assigned when the physical bib is issued.</div></div><button class="btn btn-outline-secondary btn-sm" type="button" id="toggleProvisional">+ Add New BDC Competitor</button></div><form id="addEntryForm"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="token" value="<?=e($token)?>"><input type="hidden" name="round_id" value="<?=$roundId?>"><input type="hidden" name="action" value="add_entry"><input type="hidden" name="entry_mode" id="entryMode" value="existing"><div class="row g-3"><div class="col-md-2"><label class="form-label">Role</label><select class="form-select" name="dance_role" id="addRole" required><option value="leader">Leader</option><option value="follower">Follower</option></select></div><div class="col-md-6 position-relative"><label class="form-label" id="competitorLabel">BDC competitor</label><input class="form-control" name="competitor_search" id="competitorSearch" autocomplete="off" placeholder="Search by exact name or BDC ID" required><div id="competitorMatches" class="list-group position-absolute w-100 shadow" style="z-index:20;max-height:280px;overflow:auto"></div></div><div class="col-md-2"><label class="form-label">Bib number</label><input class="form-control" type="number" min="1" name="bib" placeholder="Optional · assign later"></div><div class="col-md-2 d-flex align-items-end"><button class="btn btn-dark w-100" id="addEntryButton" type="submit">Add competitor</button></div><div class="col-12 d-none" id="provisionalFields"><div class="alert alert-warning py-2 mb-2">Use only when the dancer or updated points are genuinely missing from BDC. The provisional record and reason are audited.</div><label class="form-label">Reason for provisional entry</label><input class="form-control" name="override_reason" placeholder="Example: points from the latest event are awaiting import"></div></div></form></div></div><div class="row g-4 mt-1" id="rolePanels"></div></main>
<script>
const token=<?=json_encode($token)?>,roundId=<?=$roundId?>,csrf=<?=json_encode($csrf)?>,initial=<?=json_encode($entries,JSON_UNESCAPED_SLASHES)?>;let entries=initial,lastUpdate='',saving=false;
const esc=s=>String(s??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
function render(){const q=document.querySelector('#deskSearch').value.trim().toLowerCase();document.querySelector('#rolePanels').innerHTML=['leader','follower'].map(role=>{const rows=entries.filter(e=>e.dance_role===role&&(!q||`${e.display_name} ${e.bdc_id??''} ${e.bib_number??''}`.toLowerCase().includes(q)));const active=rows.filter(e=>e.entry_status==='active'),ready=active.filter(e=>+e.desk_ready).length,missing=active.filter(e=>!+e.bib_number).length;return `<div class="col-xl-6"><div class="card shadow-sm"><div class="card-body"><div class="d-flex justify-content-between"><h2 class="h5">${role==='leader'?'Leaders':'Followers'}</h2><span class="badge text-bg-primary">${active.length} total · ${ready} ready · ${missing} missing bib</span></div><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Bib</th><th>Name</th><th>Check in</th><th>Ready</th><th>Status</th></tr></thead><tbody>${rows.map(row=>`<tr data-entry="${row.id}" class="${+row.desk_ready?'entry-ready':''} ${row.entry_status==='withdrawn'?'entry-withdrawn':''}"><td><input class="form-control form-control-sm bib" type="number" min="1" value="${esc(row.bib_number??'')}" style="width:85px"></td><td><strong>${esc(row.display_name)}</strong><div class="small text-muted">${esc(row.bdc_id??'')}</div></td><td><input class="form-check-input checked" type="checkbox" ${+row.desk_checked_in?'checked':''}></td><td><input class="form-check-input ready" type="checkbox" ${+row.desk_ready?'checked':''}></td><td><select class="form-select form-select-sm status"><option value="active" ${row.entry_status==='active'?'selected':''}>Active</option><option value="withdrawn" ${row.entry_status==='withdrawn'?'selected':''}>Withdrawn</option></select></td></tr>`).join('')||'<tr><td colspan="5" class="text-muted">No competitors</td></tr>'}</tbody></table></div></div></div></div>`}).join('');const active=entries.filter(e=>e.entry_status==='active');document.querySelector('#summary').textContent=`${active.length} active competitors`;bind();}
function bind(){document.querySelectorAll('tr[data-entry]').forEach(tr=>tr.querySelectorAll('input,select').forEach(el=>el.addEventListener('change',()=>save(tr))));}
async function save(tr){saving=true;const body=new FormData();body.set('_csrf',csrf);body.set('token',token);body.set('round_id',roundId);body.set('action','update_entry');body.set('entry_id',tr.dataset.entry);body.set('bib',tr.querySelector('.bib').value);body.set('checked',tr.querySelector('.checked').checked?'1':'0');body.set('ready',tr.querySelector('.ready').checked?'1':'0');body.set('status',tr.querySelector('.status').value);try{const r=await fetch(location.href,{method:'POST',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},body});const data=await r.json();if(!r.ok||!data.ok)throw new Error(data.error||'Save failed');entries=data.entries;render();notice('Saved','success')}catch(e){notice(e.message,'danger');await poll(true)}finally{saving=false}}
function notice(message,type){document.querySelector('#notice').innerHTML=`<div class="alert alert-${type} py-2">${esc(message)}</div>`;setTimeout(()=>document.querySelector('#notice').innerHTML='',2500)}
let searchTimer=null;
async function searchCompetitors(){const term=document.querySelector('#competitorSearch').value.trim(),role=document.querySelector('#addRole').value,box=document.querySelector('#competitorMatches');box.innerHTML='';if(document.querySelector('#entryMode').value!=='existing'||term.length<2)return;try{const u=new URL(location.href);u.searchParams.set('competitor_search',term);u.searchParams.set('role',role);const r=await fetch(u,{headers:{Accept:'application/json'}}),data=await r.json();box.innerHTML=(data.competitors||[]).map(c=>`<button type="button" class="list-group-item list-group-item-action ${c.eligible?'':'list-group-item-danger'}" data-value="${esc(c.bdc_id)}" ${c.eligible?'':'disabled'}><strong>${esc(c.name)}</strong> <span class="small">${esc(c.bdc_id)}</span><div class="small">${esc(c.eligible?'Eligible for this division':c.reason)}</div></button>`).join('')||'<div class="list-group-item text-muted">No matching BDC competitor</div>';box.querySelectorAll('button[data-value]').forEach(button=>button.addEventListener('click',()=>{document.querySelector('#competitorSearch').value=button.dataset.value;box.innerHTML=''}))}catch(e){box.innerHTML=`<div class="list-group-item text-danger">${esc(e.message)}</div>`}}
async function addEntry(event){event.preventDefault();const form=event.currentTarget,button=document.querySelector('#addEntryButton');button.disabled=true;button.textContent='Adding…';try{const r=await fetch(location.href,{method:'POST',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},body:new FormData(form)}),data=await r.json();if(!r.ok||!data.ok)throw new Error(data.error||'Could not add competitor');entries=data.entries;render();form.reset();document.querySelector('#entryMode').value='existing';document.querySelector('#provisionalFields').classList.add('d-none');document.querySelector('#competitorLabel').textContent='BDC competitor';document.querySelector('#toggleProvisional').textContent='+ Add New BDC Competitor';document.querySelector('#competitorMatches').innerHTML='';notice('Competitor added'+(form.querySelector('[name="bib"]').value?' and bib assigned':' · bib unassigned'),'success')}catch(e){notice(e.message,'danger')}finally{button.disabled=false;button.textContent='Add competitor'}}
document.querySelector('#addEntryForm').addEventListener('submit',addEntry);
document.querySelector('#competitorSearch').addEventListener('input',()=>{clearTimeout(searchTimer);searchTimer=setTimeout(searchCompetitors,250)});
document.querySelector('#addRole').addEventListener('change',searchCompetitors);
document.querySelector('#toggleProvisional').addEventListener('click',event=>{const input=document.querySelector('#entryMode'),provisional=input.value!=='create';input.value=provisional?'create':'existing';document.querySelector('#provisionalFields').classList.toggle('d-none',!provisional);document.querySelector('#competitorLabel').textContent=provisional?'Competitor full name':'BDC competitor';event.currentTarget.textContent=provisional?'Return to BDC search':'+ Add New BDC Competitor';document.querySelector('#competitorMatches').innerHTML='';document.querySelector('#competitorSearch').value=''});
async function poll(force=false){if(saving)return;try{const u=new URL(location.href);u.searchParams.set('live','1');u.searchParams.set('since',force?'':lastUpdate);const r=await fetch(u,{headers:{Accept:'application/json'}});const data=await r.json();if(data.changed){entries=data.entries;render()}lastUpdate=data.latest||lastUpdate;document.querySelector('#liveState').className='badge text-bg-success';document.querySelector('#liveState').textContent='Live'}catch{document.querySelector('#liveState').className='badge text-bg-warning';document.querySelector('#liveState').textContent='Reconnecting'}}
document.querySelector('#deskSearch').addEventListener('input',render);render();poll(true);setInterval(()=>poll(false),3000);
</script></body></html>
