<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';

use App\Core\Csrf;
use App\Core\Database;

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

$isAjax=str_contains((string)($_SERVER['HTTP_ACCEPT']??''),'application/json')||($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='XMLHttpRequest';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Invalid security token. Refresh the desk and try again.');
  $action=(string)($_POST['action']??'');$entryId=(int)($_POST['entry_id']??0);
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
<main class="container py-3"><div id="notice"></div><div class="sticky-tools"><div class="card shadow-sm"><div class="card-body py-2 d-flex flex-wrap gap-2 align-items-center"><input id="deskSearch" class="form-control" style="max-width:360px" placeholder="Search name, BDC ID or bib"><span id="liveState" class="badge text-bg-success">Live</span><span id="summary" class="small text-muted"></span></div></div></div><div class="row g-4 mt-1" id="rolePanels"></div></main>
<script>
const token=<?=json_encode($token)?>,roundId=<?=$roundId?>,csrf=<?=json_encode($csrf)?>,initial=<?=json_encode($entries,JSON_UNESCAPED_SLASHES)?>;let entries=initial,lastUpdate='',saving=false;
const esc=s=>String(s??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
function render(){const q=document.querySelector('#deskSearch').value.trim().toLowerCase();document.querySelector('#rolePanels').innerHTML=['leader','follower'].map(role=>{const rows=entries.filter(e=>e.dance_role===role&&(!q||`${e.display_name} ${e.bdc_id??''} ${e.bib_number??''}`.toLowerCase().includes(q)));const active=rows.filter(e=>e.entry_status==='active'),ready=active.filter(e=>+e.desk_ready).length,missing=active.filter(e=>!+e.bib_number).length;return `<div class="col-xl-6"><div class="card shadow-sm"><div class="card-body"><div class="d-flex justify-content-between"><h2 class="h5">${role==='leader'?'Leaders':'Followers'}</h2><span class="badge text-bg-primary">${active.length} total · ${ready} ready · ${missing} missing bib</span></div><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Bib</th><th>Name</th><th>Check in</th><th>Ready</th><th>Status</th></tr></thead><tbody>${rows.map(row=>`<tr data-entry="${row.id}" class="${+row.desk_ready?'entry-ready':''} ${row.entry_status==='withdrawn'?'entry-withdrawn':''}"><td><input class="form-control form-control-sm bib" type="number" min="1" value="${esc(row.bib_number??'')}" style="width:85px"></td><td><strong>${esc(row.display_name)}</strong><div class="small text-muted">${esc(row.bdc_id??'')}</div></td><td><input class="form-check-input checked" type="checkbox" ${+row.desk_checked_in?'checked':''}></td><td><input class="form-check-input ready" type="checkbox" ${+row.desk_ready?'checked':''}></td><td><select class="form-select form-select-sm status"><option value="active" ${row.entry_status==='active'?'selected':''}>Active</option><option value="withdrawn" ${row.entry_status==='withdrawn'?'selected':''}>Withdrawn</option></select></td></tr>`).join('')||'<tr><td colspan="5" class="text-muted">No competitors</td></tr>'}</tbody></table></div></div></div></div>`}).join('');const active=entries.filter(e=>e.entry_status==='active');document.querySelector('#summary').textContent=`${active.length} active competitors`;bind();}
function bind(){document.querySelectorAll('tr[data-entry]').forEach(tr=>tr.querySelectorAll('input,select').forEach(el=>el.addEventListener('change',()=>save(tr))));}
async function save(tr){saving=true;const body=new FormData();body.set('_csrf',csrf);body.set('token',token);body.set('round_id',roundId);body.set('action','update_entry');body.set('entry_id',tr.dataset.entry);body.set('bib',tr.querySelector('.bib').value);body.set('checked',tr.querySelector('.checked').checked?'1':'0');body.set('ready',tr.querySelector('.ready').checked?'1':'0');body.set('status',tr.querySelector('.status').value);try{const r=await fetch(location.href,{method:'POST',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},body});const data=await r.json();if(!r.ok||!data.ok)throw new Error(data.error||'Save failed');entries=data.entries;render();notice('Saved','success')}catch(e){notice(e.message,'danger');await poll(true)}finally{saving=false}}
function notice(message,type){document.querySelector('#notice').innerHTML=`<div class="alert alert-${type} py-2">${esc(message)}</div>`;setTimeout(()=>document.querySelector('#notice').innerHTML='',2500)}
async function poll(force=false){if(saving)return;try{const u=new URL(location.href);u.searchParams.set('live','1');u.searchParams.set('since',force?'':lastUpdate);const r=await fetch(u,{headers:{Accept:'application/json'}});const data=await r.json();if(data.changed){entries=data.entries;render()}lastUpdate=data.latest||lastUpdate;document.querySelector('#liveState').className='badge text-bg-success';document.querySelector('#liveState').textContent='Live'}catch{document.querySelector('#liveState').className='badge text-bg-warning';document.querySelector('#liveState').textContent='Reconnecting'}}
document.querySelector('#deskSearch').addEventListener('input',render);render();poll(true);setInterval(()=>poll(false),3000);
</script></body></html>
