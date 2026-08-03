<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use App\Core\Database;
use App\Services\SchemaUpdater;
$pdo=Database::connection(); SchemaUpdater::run($pdo);
$id=(int)($_GET['id']??0);
$requestedGroup=(int)($_GET['career_group_id']??0);

if($requestedGroup>0){
 $s=$pdo->prepare("SELECT c.*,g.display_name career_display_name FROM bdc_competitors c JOIN bdc_competitor_career_groups g ON g.id=c.career_group_id WHERE c.career_group_id=:g AND c.status='active' ORDER BY FIELD(c.dance_role,'follower','leader'),c.id");
 $s->execute(['g'=>$requestedGroup]);$profiles=$s->fetchAll();
}else{
 $s=$pdo->prepare("SELECT c.*,g.display_name career_display_name FROM bdc_competitors c LEFT JOIN bdc_competitor_career_groups g ON g.id=c.career_group_id WHERE c.id=:id AND c.status='active'");
 $s->execute(['id'=>$id]);$first=$s->fetch();
 if(!$first){http_response_code(404);exit('Competitor not found');}
 if(!empty($first['career_group_id'])){
  $s=$pdo->prepare("SELECT c.*,g.display_name career_display_name FROM bdc_competitors c JOIN bdc_competitor_career_groups g ON g.id=c.career_group_id WHERE c.career_group_id=:g AND c.status='active' ORDER BY FIELD(c.dance_role,'follower','leader'),c.id");
  $s->execute(['g'=>(int)$first['career_group_id']]);$profiles=$s->fetchAll();
 }else{$profiles=[$first];}
}
if(!$profiles){http_response_code(404);exit('Career profile not found');}
$primary=$profiles[0];
$displayName=trim((string)($primary['career_display_name']??''))?:$primary['exact_name'];
$ids=array_map(fn($p)=>(int)$p['id'],$profiles);
$rows=[];$roleStats=[];$totalPoints=0.0;$events=0;$wins=0;$podiums=0;$finals=0;
foreach($profiles as $p){
 $pid=(int)$p['id'];$role=(string)$p['dance_role'];
 $h=$pdo->prepare("SELECT e.name event_name,e.event_date,r.division,r.dance_role,r.placement,r.finalist_status,r.partner_name,r.points_awarded FROM bdc_participant_results r LEFT JOIN bdc_events e ON e.id=r.event_id WHERE r.competitor_id=:id ORDER BY e.event_date DESC,r.id DESC");
 $h->execute(['id'=>$pid]);$personRows=$h->fetchAll();
 if(!$personRows){
  $h=$pdo->prepare("SELECT e.name event_name,e.event_date,r.division,r.dance_role,r.placement,'participant' finalist_status,NULL partner_name,r.points points_awarded FROM bdc_point_transactions r LEFT JOIN bdc_events e ON e.id=r.event_id WHERE r.competitor_id=:id ORDER BY e.event_date DESC,r.id DESC");
  $h->execute(['id'=>$pid]);$personRows=$h->fetchAll();
 }
 $rs=['points'=>0.0,'events'=>0,'wins'=>0,'podiums'=>0,'finals'=>0,'bdc_id'=>$p['bdc_id'],'name'=>$p['exact_name'],'division'=>$p['current_division']];
 foreach($personRows as $r){
  $r['_competitor_id']=$pid;$r['_bdc_id']=$p['bdc_id'];$rows[]=$r;
  $pts=(float)$r['points_awarded'];$rs['points']+=$pts;$totalPoints+=$pts;$rs['events']++;$events++;
  $pl=(int)$r['placement'];if($pl===1){$rs['wins']++;$wins++;}if($pl>=1&&$pl<=3){$rs['podiums']++;$podiums++;}if(in_array($r['finalist_status'],['finalist','placed','winner'],true)||$pl>0){$rs['finals']++;$finals++;}
 }
 $roleStats[$role]=$rs;
}
usort($rows,fn($a,$b)=>strcmp((string)$b['event_date'],(string)$a['event_date']));
$photo='';foreach($profiles as $p){if(!empty($p['photo_url'])){$photo=$p['photo_url'];break;}}$photo=$photo?:url('public/assets/img/default-competitor.svg');
$country='';foreach($profiles as $p){if(!empty($p['country'])){$country=$p['country'];break;}}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($displayName)?> | BDC Career Profile</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="<?=e(url('public/assets/css/app.css'))?>" rel="stylesheet"><style>.profile-photo{width:120px;height:150px;object-fit:cover;border-radius:12px}.stat{background:#fff;border-radius:12px;padding:1rem;box-shadow:0 .25rem .8rem rgba(0,0,0,.06)}.role-card{border-left:5px solid #212529}.role-id{font-family:monospace}</style></head><body class="bg-light"><nav class="navbar navbar-dark bg-dark"><div class="container"><a class="navbar-brand" href="<?=e(url())?>">Bachata Dance Council</a><div class="d-flex gap-2"><a class="btn btn-warning btn-sm" href="https://bachatadancecouncil.com/">🏠 BDC Home</a><a class="btn btn-outline-light btn-sm" href="<?=e(url('leaderboard/'))?>">Leaderboard</a></div></div></nav><main class="container py-5"><div class="card border-0 shadow-sm mb-4"><div class="card-body d-flex flex-column flex-md-row gap-4 align-items-md-center"><img class="profile-photo" src="<?=e($photo)?>" alt="<?=e($displayName)?>"><div class="flex-grow-1"><div class="text-uppercase small text-muted fw-semibold">Combined Career Profile</div><h1 class="mb-1"><?=e($displayName)?></h1><div class="text-muted mb-2"><?php foreach($profiles as $i=>$p):?><?= $i?' · ':'' ?><?=e($p['bdc_id'])?> (<?=e(ucfirst($p['dance_role']))?>)<?php endforeach;?></div><div><strong>Country:</strong> <?=e($country?:'—')?> &nbsp; <strong>Combined career points:</strong> <?=e((string)$totalPoints)?></div></div></div></div>
<div class="row g-3 mb-4"><div class="col-6 col-lg"><div class="stat"><div class="text-muted small">Career Points</div><div class="h3 mb-0"><?=e((string)$totalPoints)?></div></div></div><div class="col-6 col-lg"><div class="stat"><div class="text-muted small">Events</div><div class="h3 mb-0"><?=$events?></div></div></div><div class="col-6 col-lg"><div class="stat"><div class="text-muted small">Championships</div><div class="h3 mb-0"><?=$wins?></div></div></div><div class="col-6 col-lg"><div class="stat"><div class="text-muted small">Podiums</div><div class="h3 mb-0"><?=$podiums?></div></div></div><div class="col-6 col-lg"><div class="stat"><div class="text-muted small">Finals</div><div class="h3 mb-0"><?=$finals?></div></div></div></div>
<div class="row g-3 mb-4"><?php foreach($roleStats as $role=>$s):?><div class="col-md-6"><div class="card role-card h-100 shadow-sm border-0"><div class="card-body"><div class="d-flex justify-content-between"><h2 class="h5"><?=e(ucfirst($role))?> Stats</h2><span class="badge text-bg-dark"><?=e((string)$s['points'])?> points</span></div><div class="role-id text-muted mb-3"><?=e($s['bdc_id'])?></div><div class="row text-center"><div class="col"><strong><?=$s['events']?></strong><div class="small text-muted">Events</div></div><div class="col"><strong><?=$s['wins']?></strong><div class="small text-muted">Wins</div></div><div class="col"><strong><?=$s['podiums']?></strong><div class="small text-muted">Podiums</div></div><div class="col"><strong><?=$s['finals']?></strong><div class="small text-muted">Finals</div></div></div></div></div></div><?php endforeach;?></div>
<div class="card border-0 shadow-sm"><div class="card-header bg-white"><strong>Combined Competition History</strong></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Date</th><th>Event</th><th>BDC ID</th><th>Division</th><th>Role</th><th>Placement</th><th>Partner</th><th>Points</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=e($r['event_date']?:'—')?></td><td><?=e($r['event_name']?:'—')?></td><td><code><?=e($r['_bdc_id'])?></code></td><td><?=e(ucwords(str_replace('_',' ',$r['division'])))?></td><td><?=e(ucfirst($r['dance_role']))?></td><td><?=e($r['placement']?:'—')?></td><td><?=e($r['partner_name']?:'—')?></td><td class="fw-bold"><?=e((string)(float)$r['points_awarded'])?></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="8" class="text-center text-muted py-5">No results found.</td></tr><?php endif;?></tbody></table></div></div></main></body></html>
