<?php
declare(strict_types=1);

require dirname(__DIR__).'/bootstrap.php';

use App\Core\Database;

$pdo=Database::connection();
$segment=(string)($_GET['segment']??'participants');
if(!in_array($segment,['participants','repository'],true))$segment='participants';

$query=trim((string)($_GET['q']??''));
$eventId=max(0,(int)($_GET['event']??0));
$events=$pdo->query("SELECT id,name,event_date FROM bdc_events ORDER BY event_date DESC,id DESC")->fetchAll();

$rows=[];
if($segment==='repository'){
 $sql="SELECT d.*,e.name event_name,e.event_date
       FROM bdc_result_documents d
       LEFT JOIN bdc_events e ON e.id=d.event_id
       WHERE d.status='published'";
 $params=[];
 if($eventId>0){$sql.=' AND d.event_id=:event';$params['event']=$eventId;}
 if($query!==''){$sql.=' AND (d.title LIKE :document_query OR e.name LIKE :event_query)';$params['document_query']='%'.$query.'%';$params['event_query']='%'.$query.'%';}
 $sql.=' ORDER BY COALESCE(e.event_date,DATE(d.created_at)) DESC,d.id DESC';
 $stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll();
}else{
 $sql="SELECT r.*,c.exact_name,c.bdc_id,e.name event_name,e.event_date
       FROM bdc_participant_results r
       JOIN bdc_competitors c ON c.id=r.competitor_id
       JOIN bdc_events e ON e.id=r.event_id
       WHERE c.status='active'";
 $params=[];
 if($eventId>0){$sql.=' AND r.event_id=:event';$params['event']=$eventId;}
 if($query!==''){$sql.=' AND (c.exact_name LIKE :name_query OR c.bdc_id LIKE :id_query OR e.name LIKE :event_query)';$params['name_query']='%'.$query.'%';$params['id_query']='%'.$query.'%';$params['event_query']='%'.$query.'%';}
 $sql.=' ORDER BY e.event_date DESC,r.id DESC LIMIT 500';
 $stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll();
}

function repositoryUrl(array $row):string{
 $storage=trim((string)($row['storage_path']??''));
 if($storage!=='')return url('/result-file.php?file='.rawurlencode(basename($storage)));
 $target=trim((string)($row['url']??''));
 if($target==='')return '#';
 if(preg_match('#^https?://#i',$target))return $target;
 return url('/'.ltrim($target,'/'));
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $segment==='repository'?'Result Repository':'Participant Results' ?> | BDC</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?=e(url('/public/assets/css/app.css'))?>" rel="stylesheet">
<style>body{background:#f5f6f8}.results-card{border:0;border-radius:16px;box-shadow:0 8px 28px rgba(15,23,42,.08)}.hero{background:linear-gradient(135deg,#111827,#27272a);color:#fff}.filter-card{margin-top:-28px}.document-icon{font-size:1.5rem}</style>
</head>
<body>
<section class="hero py-5"><div class="container d-flex justify-content-between align-items-start gap-3 flex-wrap"><div><div class="text-uppercase small opacity-75">Bachata Dance Council</div><h1 class="display-6 fw-bold mb-2"><?= $segment==='repository'?'Result Repository':'Participant Results' ?></h1><p class="mb-0 opacity-75"><?= $segment==='repository'?'Official BDC competition documents.':'Search individual BDC competition history.' ?></p></div><a class="btn btn-outline-light" href="<?=e(url('/'))?>">BDC Home</a></div></section>
<main class="container pb-5">
 <nav class="nav nav-pills gap-2 pt-4 mb-3">
  <a class="nav-link <?=$segment==='participants'?'active':''?>" href="<?=e(url('/results/?segment=participants'))?>">Participant Results</a>
  <a class="nav-link <?=$segment==='repository'?'active':''?>" href="<?=e(url('/results/?segment=repository'))?>">Result Repository</a>
 </nav>
 <section class="card results-card filter-card mb-4"><div class="card-body"><form method="get" class="row g-3 align-items-end"><input type="hidden" name="segment" value="<?=e($segment)?>"><div class="col-md-6"><label class="form-label">Search</label><input class="form-control" name="q" value="<?=e($query)?>" placeholder="<?= $segment==='repository'?'Document or event name':'Competitor name, BDC ID or event' ?>"></div><div class="col-md-4"><label class="form-label">Event</label><select class="form-select" name="event"><option value="0">All events</option><?php foreach($events as $event):?><option value="<?=(int)$event['id']?>" <?=$eventId===(int)$event['id']?'selected':''?>><?=e($event['name'])?><?=!empty($event['event_date'])?' · '.e($event['event_date']):''?></option><?php endforeach;?></select></div><div class="col-md-2"><button class="btn btn-dark w-100">Search</button></div></form></div></section>
 <section class="card results-card"><div class="card-header bg-white d-flex justify-content-between align-items-center"><strong><?=count($rows)?> result<?=count($rows)===1?'':'s'?></strong><?php if($query!==''||$eventId>0):?><a href="<?=e(url('/results/?segment='.$segment))?>">Clear filters</a><?php endif;?></div>
 <?php if($segment==='repository'):?>
  <div class="list-group list-group-flush"><?php foreach($rows as $row):?><a class="list-group-item list-group-item-action p-4 d-flex gap-3 align-items-start" href="<?=e(repositoryUrl($row))?>" target="_blank" rel="noopener"><span class="document-icon">📄</span><span class="flex-grow-1"><strong class="d-block"><?=e($row['title'])?></strong><span class="text-muted small"><?=e($row['event_name']?:'BDC Official Result')?><?=!empty($row['event_date'])?' · '.e($row['event_date']):''?> · <?=e(strtoupper((string)$row['file_type']))?></span></span><span aria-hidden="true">↗</span></a><?php endforeach;?><?php if(!$rows):?><div class="text-center text-muted py-5">No published result documents found.</div><?php endif;?></div>
 <?php else:?>
  <div class="table-responsive"><table class="table align-middle mb-0"><thead class="table-light"><tr><th>Date</th><th>Competitor</th><th>Event</th><th>Division</th><th>Role</th><th>Placement</th><th>Partner</th><th class="text-end">Points</th></tr></thead><tbody><?php foreach($rows as $row):?><tr><td><?=e($row['event_date']?:'—')?></td><td><a class="fw-semibold text-dark" href="<?=e(url('/competitor/?id='.(int)$row['competitor_id']))?>"><?=e($row['exact_name'])?></a><div class="small text-muted"><?=e($row['bdc_id'])?></div></td><td><?=e($row['event_name'])?></td><td><?=e(ucwords(str_replace('_',' ',(string)$row['division'])))?></td><td><?=e(ucfirst((string)$row['dance_role']))?></td><td><?=e($row['placement']?:'—')?></td><td><?=e($row['partner_name']?:'—')?></td><td class="text-end fw-semibold"><?=e((string)(float)$row['points_awarded'])?></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="8" class="text-center text-muted py-5">No participant results found.</td></tr><?php endif;?></tbody></table></div>
 <?php endif;?>
 </section>
</main>
</body></html>
