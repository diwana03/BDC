<?php
declare(strict_types=1);

require dirname(__DIR__).'/bootstrap.php';

use App\Core\Database;
use App\Services\DivisionProgressionService;
use App\Services\SpecialCategoryService;

$pdo=Database::connection();

$division=(string)($_GET['division']??'novice');
$role=(string)($_GET['role']??'leader');
$showOut=!empty($_GET['show_out']);

$specialDivisions=[
 'bachata_rising'=>'Bachata Rising',
 'bachata_open'=>'Bachata Open',
 'bachata_invitational'=>'Bachata Invitational',
];
$allowedDivisions=array_merge(['all','novice','intermediate','advanced'],array_keys($specialDivisions));
$allowedRoles=['leader','follower'];

if(!in_array($division,$allowedDivisions,true))$division='novice';
if(!in_array($role,$allowedRoles,true))$role='leader';
$isSpecial=SpecialCategoryService::isSpecial($division);
if($isSpecial)$showOut=false;

$rows=[];

if($isSpecial){
 $stmt=$pdo->prepare("
  SELECT
   COALESCE(c.career_group_id,-c.id) career_key,
   c.id competitor_id,
   c.bdc_id,
   COALESCE(g.display_name,c.exact_name) competitor_name,
   c.country,
   c.photo_url,
   c.division_override_reason,
   sr.event_id,
   e.event_date,
   fr.final_rank
  FROM bdc_scoring_rounds sr
  JOIN bdc_scoring_final_results fr ON fr.round_id=sr.id
  JOIN bdc_scoring_final_pairs fp ON fp.id=fr.pair_id AND fp.round_id=sr.id
  JOIN bdc_scoring_entries se
   ON se.id=CASE WHEN :entry_role='leader' THEN fp.leader_entry_id ELSE fp.follower_entry_id END
  JOIN bdc_competitors c ON c.id=se.competitor_id
  LEFT JOIN bdc_competitor_career_groups g ON g.id=c.career_group_id
  JOIN bdc_events e ON e.id=sr.event_id
  WHERE sr.division=:division
   AND sr.round_type='final'
   AND se.dance_role=:dance_role
   AND c.status='active'
   AND c.show_on_leaderboard=1
   AND EXISTS(
    SELECT 1
    FROM bdc_scoring_publications publication
    WHERE publication.final_round_id=sr.id
     AND publication.status='published'
   )
  ORDER BY e.event_date,fr.final_rank
 ");
 $stmt->execute(['entry_role'=>$role,'division'=>$division,'dance_role'=>$role]);

 $aggregate=[];
 foreach($stmt->fetchAll() as $result){
  $rank=(int)$result['final_rank'];
  $points=SpecialCategoryService::fixedPoints($division,$rank);
  if($points<=0.0)continue;
  $key=(string)$result['career_key'];
  if(!isset($aggregate[$key])){
   $aggregate[$key]=[
    'competitor_id'=>(int)$result['competitor_id'],
    'bdc_ids'=>[],
    'competitor_name'=>(string)$result['competitor_name'],
    'country'=>(string)($result['country']??''),
    'photo_url'=>(string)($result['photo_url']??''),
    'division_override_reason'=>(string)($result['division_override_reason']??''),
    'total_points'=>0.0,
    'selected_events'=>0,
    'event_ids'=>[],
    'first_place_count'=>0,
    'second_place_count'=>0,
    'third_place_count'=>0,
    'last_result_date'=>'',
    'eligible'=>true,
    'status_label'=>'Special Category',
   ];
  }
  if(!empty($result['bdc_id']))$aggregate[$key]['bdc_ids'][(string)$result['bdc_id']]=true;
  $aggregate[$key]['total_points']+=(float)$points;
  $eventId=(int)$result['event_id'];
  if(!isset($aggregate[$key]['event_ids'][$eventId])){
   $aggregate[$key]['event_ids'][$eventId]=true;
   $aggregate[$key]['selected_events']++;
  }
  if($rank===1)$aggregate[$key]['first_place_count']++;
  if($rank===2)$aggregate[$key]['second_place_count']++;
  if($rank===3)$aggregate[$key]['third_place_count']++;
  $date=(string)($result['event_date']??'');
  if($date>$aggregate[$key]['last_result_date'])$aggregate[$key]['last_result_date']=$date;
 }
 foreach($aggregate as $row){
  $row['bdc_id']=implode(' / ',array_keys($row['bdc_ids']));
  unset($row['bdc_ids'],$row['event_ids']);
  $rows[]=$row;
 }
}else{
 $roleJoin=$division==='all'?"AND pt.dance_role IN ('leader','follower')":'AND pt.dance_role=:role';
 $selectedEventsCondition=$division==='all'?'1=1':'pt.division=:selected_division_events';
 $selectedFirstCondition=$division==='all'?'1=1':'pt.division=:selected_division_first';
 $selectedSecondCondition=$division==='all'?'1=1':'pt.division=:selected_division_second';
 $selectedThirdCondition=$division==='all'?'1=1':'pt.division=:selected_division_third';
 $selectedDateCondition=$division==='all'?'1=1':'pt.division=:selected_division_date';

 $stmt=$pdo->prepare("
  SELECT
   MIN(c.id) competitor_id,
   GROUP_CONCAT(DISTINCT c.bdc_id ORDER BY c.bdc_id SEPARATOR ' / ') bdc_id,
   COALESCE(g.display_name,MAX(c.exact_name)) competitor_name,
   MAX(c.country) country,
   MAX(c.photo_url) photo_url,
   MAX(c.current_division) committed_division,
   MAX(c.novice_manual_out) novice_manual_out,
   MAX(c.intermediate_manual_out) intermediate_manual_out,
   MAX(c.division_override_reason) division_override_reason,
   ROUND(SUM(CASE WHEN pt.division='novice' THEN pt.points ELSE 0 END),2) novice_points,
   ROUND(SUM(CASE WHEN pt.division='intermediate' THEN pt.points ELSE 0 END),2) intermediate_points,
   ROUND(SUM(CASE WHEN pt.division='advanced' THEN pt.points ELSE 0 END),2) advanced_points,
   ROUND(SUM(CASE WHEN pt.dance_role='leader' THEN pt.points ELSE 0 END),2) leader_points,
   ROUND(SUM(CASE WHEN pt.dance_role='follower' THEN pt.points ELSE 0 END),2) follower_points,
   MAX(CASE WHEN pt.division='intermediate' THEN 1 ELSE 0 END) competed_intermediate,
   MAX(CASE WHEN pt.division='advanced' THEN 1 ELSE 0 END) competed_advanced,
   COUNT(DISTINCT CASE WHEN $selectedEventsCondition THEN pt.event_id END) selected_events,
   COUNT(DISTINCT CASE WHEN $selectedFirstCondition AND LOWER(TRIM(pt.placement)) IN('1','1st','first') THEN pt.event_id END) first_place_count,
   COUNT(DISTINCT CASE WHEN $selectedSecondCondition AND LOWER(TRIM(pt.placement)) IN('2','2nd','second') THEN pt.event_id END) second_place_count,
   COUNT(DISTINCT CASE WHEN $selectedThirdCondition AND LOWER(TRIM(pt.placement)) IN('3','3rd','third') THEN pt.event_id END) third_place_count,
   MAX(CASE WHEN $selectedDateCondition THEN COALESCE(e.event_date,DATE(pt.created_at)) END) last_result_date
  FROM bdc_competitors c
  LEFT JOIN bdc_point_transactions pt
   ON pt.competitor_id=c.id
   $roleJoin
   AND NOT EXISTS (
    SELECT 1
    FROM bdc_point_transactions duplicate_pt
    JOIN bdc_competitors duplicate_c ON duplicate_c.id=duplicate_pt.competitor_id
    WHERE COALESCE(duplicate_c.career_group_id,-duplicate_c.id)=COALESCE(c.career_group_id,-c.id)
     AND duplicate_pt.event_id <=> pt.event_id
     AND duplicate_pt.division=pt.division
     AND duplicate_pt.dance_role=pt.dance_role
     AND COALESCE(LOWER(TRIM(duplicate_pt.placement)),'')=COALESCE(LOWER(TRIM(pt.placement)),'')
     AND duplicate_pt.points=pt.points
     AND duplicate_pt.id<pt.id
   )
  LEFT JOIN bdc_events e ON e.id=pt.event_id
  LEFT JOIN bdc_competitor_career_groups g ON g.id=c.career_group_id
  WHERE c.status='active' AND c.show_on_leaderboard=1
  GROUP BY COALESCE(c.career_group_id,-c.id),g.display_name
 ");
 $params=[];
 if($division!=='all'){
  $params['selected_division_events']=$division;
  $params['selected_division_first']=$division;
  $params['selected_division_second']=$division;
  $params['selected_division_third']=$division;
  $params['selected_division_date']=$division;
  $params['role']=$role;
 }
 $stmt->execute($params);

 foreach($stmt->fetchAll() as $row){
  $novice=(float)$row['novice_points'];
  $intermediate=(float)$row['intermediate_points'];
  $advanced=(float)$row['advanced_points'];

  $points=$division==='all'
   ?(float)$row['leader_points']+(float)$row['follower_points']
   :DivisionProgressionService::selectedPoints($division,$novice,$intermediate,$advanced);
  if($points<=0.0)continue;

  $eligibility=$division==='all'?['eligible'=>true,'reason'=>'','promoted_to'=>null]:DivisionProgressionService::eligibilityFor(
   $division,$novice,$intermediate,$advanced,$row['committed_division'],(bool)$row['competed_intermediate'],(bool)$row['competed_advanced']
  );
  $manualOut=($division==='novice' && (bool)$row['novice_manual_out'])||($division==='intermediate' && (bool)$row['intermediate_manual_out']);
  $eligible=$eligibility['eligible']&&!$manualOut;
  if(!$eligible && !$showOut)continue;

  $row['eligible']=$eligible;
  $promotedTo=$manualOut?match($division){'novice'=>'intermediate','intermediate'=>'advanced',default=>null}:$eligibility['promoted_to'];
  $row['status_label']=$promotedTo!==null?'Promoted to '.DivisionProgressionService::label($promotedTo):($eligibility['eligible']?'In Division':ucfirst($eligibility['reason']));
  $row['total_points']=$points;
  $rows[]=$row;
 }
}

usort($rows,function(array $a,array $b):int{
 $points=(float)$b['total_points']<=>(float)$a['total_points'];
 if($points!==0)return $points;
 $date=strcmp((string)$b['last_result_date'],(string)$a['last_result_date']);
 if($date!==0)return $date;
 $events=(int)$b['selected_events']<=>(int)$a['selected_events'];
 if($events!==0)return $events;
 return strcmp((string)$a['bdc_id'],(string)$b['bdc_id']);
});

foreach($rows as $index=>&$row)$row['rank']=$index+1;
unset($row);

function leaderboardLabel(string $value):string{
 if($value==='all')return 'All Divisions';
 if(SpecialCategoryService::isSpecial($value))return SpecialCategoryService::label($value);
 return DivisionProgressionService::label($value);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>BDC Leaderboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f5f6f8;color:#20242a}
.hero{background:linear-gradient(135deg,#111827,#27272a);color:#fff}
.board-card{border:0;border-radius:16px;box-shadow:0 8px 28px rgba(15,23,42,.08)}
.rank-badge{display:inline-flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:50%;font-weight:800;background:#eceff3}
.rank-1{background:#ffd76a}.rank-2{background:#dce2e8}.rank-3{background:#e5b27d}
.avatar{width:48px;height:48px;border-radius:50%;object-fit:cover;background:#eceff3}
.points{font-size:1.15rem;font-weight:800;white-space:nowrap}
.points-breakdown{font-size:.75rem;font-weight:600;color:#6b7280;white-space:nowrap}
.table>:not(caption)>*>*{padding:.85rem .75rem}
.filter-card{margin-top:-28px}
.filter-label{font-size:.78rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#6b7280}
.filter-buttons{display:flex;gap:.5rem;flex-wrap:wrap}
.filter-buttons .btn{min-width:110px}
.out-row{background:#fff8e1}
.rule-note{font-size:.88rem}
@media(max-width:575.98px){.filter-buttons{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}.filter-buttons.divisions{grid-template-columns:repeat(2,minmax(0,1fr))}.filter-buttons .btn{min-width:0;padding:.6rem .35rem}.filter-card .card-body{padding:1rem}}
</style>
</head>
<body>
<section class="hero py-5">
 <div class="container">
  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
   <div>
    <div class="text-uppercase small opacity-75">Bachata Dance Council</div>
    <h1 class="display-6 fw-bold mb-2">Leaderboard</h1>
    <p class="mb-0 opacity-75">Rankings follow BDC division progression and approved points.</p>
   </div>
   <a class="btn btn-outline-light" href="<?=e(url('/'))?>">BDC Home</a>
  </div>
 </div>
</section>

<main class="container pb-5">
 <section class="card board-card filter-card mb-4">
  <div class="card-body">
   <form method="get" class="row g-3" id="leaderboard-filters">
    <div class="col-12">
     <div class="filter-label mb-2">Division</div>
     <div class="filter-buttons divisions">
      <?php foreach(['all'=>'All Divisions','novice'=>'Novice','intermediate'=>'Intermediate','advanced'=>'Advanced'] as $value=>$label):?>
       <a class="btn <?=$division===$value?'btn-dark':'btn-outline-dark'?>" href="?division=<?=$value?>&amp;role=<?=$role?><?=$showOut?'&amp;show_out=1':''?>"><?=$label?></a>
      <?php endforeach;?>
     </div>
    </div>
    <div class="col-12">
     <div class="filter-label mb-2">Special Category</div>
     <div class="filter-buttons divisions">
      <?php foreach($specialDivisions as $value=>$label):?>
       <a class="btn <?=$division===$value?'btn-dark':'btn-outline-dark'?>" href="?division=<?=$value?>&amp;role=<?=$role?>"><?=$label?></a>
      <?php endforeach;?>
     </div>
    </div>
    <?php if($division!=='all'):?><div class="col-12">
     <div class="filter-label mb-2">Role</div>
     <div class="filter-buttons">
      <?php foreach(['leader'=>'Leader','follower'=>'Follower'] as $value=>$label):?>
       <a class="btn <?=$role===$value?'btn-dark':'btn-outline-dark'?>" href="?division=<?=$division?>&amp;role=<?=$value?><?=(!$isSpecial&&$showOut)?'&amp;show_out=1':''?>"><?=$label?></a>
      <?php endforeach;?>
     </div>
    </div><?php endif;?>
    <?php if($division!=='all'&&!$isSpecial):?><div class="col-12">
     <input type="hidden" name="division" value="<?=e($division)?>">
     <input type="hidden" name="role" value="<?=e($role)?>">
     <label class="form-check form-switch">
      <input class="form-check-input" type="checkbox" name="show_out" value="1" <?=$showOut?'checked':''?> data-auto-submit>
      <span class="form-check-label">Show Out of Division competitors</span>
     </label>
    </div><?php endif;?>
   </form>
  </div>
 </section>

 <section class="card board-card">
  <div class="card-body p-0">
   <div class="d-flex justify-content-between align-items-center p-4 border-bottom gap-3 flex-wrap">
    <div>
     <h2 class="h4 mb-1"><?=e(leaderboardLabel($division))?><?=$division==='all'?'':' · '.e(ucfirst($role))?></h2>
     <div class="text-muted"><?=count($rows)?> ranked competitor<?=count($rows)===1?'':'s'?></div>
    </div>
    <span class="badge text-bg-success">Live</span>
   </div>

   <?php if($showOut && $division!=='all'&&!$isSpecial):?>
   <div class="alert alert-warning rounded-0 border-start-0 border-end-0 border-top-0 mb-0 px-4 py-3">
    <strong>Out of Division:</strong> A competitor is promoted when they exceed the maximum points for this division or have already competed in a higher division.
   </div>
   <?php endif;?>

   <div class="px-4 py-3 border-bottom bg-light rule-note">
    <?php if($division==='all'):?>
     Career totals combine approved Leader and Follower points across every division. The role breakdown is shown below each total.
    <?php elseif($division==='novice'):?>
     Novice dancers may move to Intermediate from 20 points and leave Novice after 25 points.
    <?php elseif($division==='intermediate'):?>
     Intermediate requires 20 Novice points. Dancers may move to Advanced from 25 Intermediate points and leave Intermediate after 30 points.
    <?php elseif($division==='advanced'):?>
     Advanced requires 25 Intermediate points and must be left after 40 Advanced points.
    <?php elseif($division==='bachata_rising'||$division==='bachata_open'):?>
     Special-category ranking uses published Final placements only. Fixed points are 5, 4, 3, 2 and 1 for 1st through 5th. These rankings do not change BDC division eligibility.
    <?php else:?>
     Bachata Invitational ranking uses published Final placements only. Fixed points are 3, 2 and 1 for 1st through 3rd. These rankings do not change BDC division eligibility.
    <?php endif;?>
   </div>

   <div class="table-responsive">
    <table class="table align-middle mb-0">
     <thead class="table-light">
      <tr><th style="width:90px">Rank</th><th>Competitor</th><th>Country</th><th class="text-end"><?=e(leaderboardLabel($division))?> Points</th><th>Events</th><th class="text-center">🥇 1st</th><th class="text-center">🥈 2nd</th><th class="text-center">🥉 3rd</th><th>Last Result</th></tr>
     </thead>
     <tbody>
     <?php foreach($rows as $row):?>
      <tr class="<?=empty($row['eligible'])?'out-row':''?>">
       <td><span class="rank-badge rank-<?=(int)$row['rank']<=3?(int)$row['rank']:0?>"><?=(int)$row['rank']?></span></td>
       <td>
        <div class="d-flex align-items-center gap-3">
         <img class="avatar" src="<?=e($row['photo_url']?:url('/public/assets/img/default-competitor.svg'))?>" alt="">
         <div>
          <strong><?=e($row['competitor_name'])?></strong>
          <?php if($row['bdc_id']):?><div class="small text-muted">BDC <?=e($row['bdc_id'])?></div><?php endif;?>
          <?php if(empty($row['eligible'])):?><div class="mt-1"><span class="badge text-bg-warning"><?=e($row['status_label'])?></span></div><?php endif;?>
          <?php if(!empty($row['division_override_reason'])):?><div class="small text-muted"><?=e($row['division_override_reason'])?></div><?php endif;?>
         </div>
        </div>
       </td>
       <td><?=e((string)($row['country']?:'—'))?></td>
       <td class="text-end points"><?=number_format((float)$row['total_points'],1)?><?php if($division==='all'):?><div class="points-breakdown">L <?=number_format((float)$row['leader_points'],1)?> + F <?=number_format((float)$row['follower_points'],1)?></div><?php endif;?></td>
       <td><?=(int)$row['selected_events']?></td>
       <td class="text-center fw-semibold"><?=(int)$row['first_place_count']?></td>
       <td class="text-center fw-semibold"><?=(int)$row['second_place_count']?></td>
       <td class="text-center fw-semibold"><?=(int)$row['third_place_count']?></td>
       <td><?=e((string)($row['last_result_date']?:'—'))?></td>
      </tr>
     <?php endforeach;?>
     <?php if(!$rows):?><tr><td colspan="9" class="text-center py-5 text-muted">No published ranking points are available for this category and role.</td></tr><?php endif;?>
     </tbody>
    </table>
   </div>
  </div>
 </section>
</main>
<script>
document.querySelectorAll('[data-auto-submit]').forEach(function(control){control.addEventListener('change',function(){document.getElementById('leaderboard-filters').submit();});});
</script>
</body>
</html>