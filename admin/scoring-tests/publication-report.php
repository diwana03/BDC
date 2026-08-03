<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Services\SchemaUpdater;
use App\Services\HtmlSnapshotToken;
use App\Services\PdfExportToken;

$pdo=Database::connection();
SchemaUpdater::run($pdo);

$roundId=(int)($_GET['round_id']??0);
$isRepositorySnapshot=HtmlSnapshotToken::verify($pdo,'points',$roundId,$_GET);
if(!$isRepositorySnapshot){Auth::requireAdmin();}
if(!PdfExportToken::verify($pdo,'points',$roundId,$_GET)){Auth::requireAdmin();}

$roundStmt=$pdo->prepare("
 SELECT r.*,e.name AS event_name,e.event_date,e.venue,e.points_tier AS event_points_tier
 FROM bdc_test_scoring_rounds r
 JOIN bdc_test_events e ON e.id=r.event_id
 WHERE r.id=:id AND r.round_type='final'
");
$roundStmt->execute(['id'=>$roundId]);
$round=$roundStmt->fetch();

if(!$round){
 http_response_code(404);
 exit('Final round not found.');
}

function reportOrdinal(int $number):string{
 if($number%100>=11&&$number%100<=13)return $number.'th';
 return $number.([1=>'st',2=>'nd',3=>'rd'][$number%10]??'th');
}

function reportPoints(int $tier,int $rank,int $count):float{
 $matrix=[
  1=>[1=>5,2=>4,3=>3,4=>2,5=>1],
  2=>[1=>10,2=>8,3=>6,4=>4,5=>2],
  3=>[1=>15,2=>12,3=>10,4=>8,5=>6],
 ];
 if(isset($matrix[$tier][$rank]))return(float)$matrix[$tier][$rank];
 if($tier===2&&$rank>=6&&$rank<=10)return 1;
 if($tier===3&&$rank>=6&&$rank<=$count)return 2;
 return 0;
}

$tierStmt=$pdo->prepare("
 SELECT points_tier
 FROM bdc_test_scoring_publications
 WHERE final_round_id=:r
 ORDER BY id DESC
 LIMIT 1
");
$tierStmt->execute(['r'=>$roundId]);
$tier=(int)$tierStmt->fetchColumn();

if($tier<1){
 $tierStmt=$pdo->prepare("
  SELECT points_tier
  FROM bdc_event_points_tiers
  WHERE event_id=:e AND division=:d
  ORDER BY FIELD(dance_role,'both','leader','follower'),id
  LIMIT 1
 ");
 $tierStmt->execute([
  'e'=>$round['event_id'],
  'd'=>$round['division'],
 ]);
 $tier=(int)$tierStmt->fetchColumn();
}

if($tier<1)$tier=(int)$round['event_points_tier'];

if(!in_array($tier,[1,2,3],true)){
 $heatsStmt=$pdo->prepare("
  SELECT id
  FROM bdc_test_scoring_rounds
  WHERE event_id=:e AND division=:d AND round_type='heats'
  ORDER BY id ASC
  LIMIT 1
 ");
 $heatsStmt->execute([
  'e'=>$round['event_id'],
  'd'=>$round['division'],
 ]);
 $heatsId=(int)$heatsStmt->fetchColumn();

 $countStmt=$pdo->prepare("
  SELECT MAX(total)
  FROM (
   SELECT COUNT(*) AS total
   FROM bdc_test_scoring_entries
   WHERE round_id=:r AND entry_status='active'
   GROUP BY dance_role
  ) counts
 ");
 $countStmt->execute(['r'=>$heatsId]);
 $competitorCount=(int)$countStmt->fetchColumn();
 $tier=$competitorCount<=15?1:($competitorCount<=30?2:3);
}

$pairStmt=$pdo->prepare("
 SELECT
  fp.pair_number,
  le.display_name AS leader_name,
  le.bib_number AS leader_bib,
  fe.display_name AS follower_name,
  fe.bib_number AS follower_bib,
  fr.final_rank
 FROM bdc_test_scoring_final_pairs fp
 JOIN bdc_test_scoring_entries le ON le.id=fp.leader_entry_id
 JOIN bdc_test_scoring_entries fe ON fe.id=fp.follower_entry_id
 JOIN bdc_test_scoring_final_results fr
   ON fr.round_id=fp.round_id
  AND fr.pair_id=fp.id
 WHERE fp.round_id=:r
 ORDER BY fr.final_rank
");
$pairStmt->execute(['r'=>$roundId]);
$pairs=$pairStmt->fetchAll();

if(!$pairs)exit('Final ranking has not been calculated yet.');

$judgeStmt=$pdo->prepare("
 SELECT judge_name,is_chief
 FROM bdc_test_scoring_judges
 WHERE round_id=:r
 ORDER BY judge_order
");
$judgeStmt->execute(['r'=>$roundId]);
$judges=$judgeStmt->fetchAll();

$chiefJudge='';
$judgeNames=[];
foreach($judges as $judge){
 $judgeNames[]=$judge['judge_name'].((int)$judge['is_chief']===1?' ★':'');
 if((int)$judge['is_chief']===1)$chiefJudge=(string)$judge['judge_name'];
}

$count=count($pairs);
$logo=url('public/assets/img/bdc-logo-header.png');
$reportStatus=$isRepositorySnapshot?'Official':'Draft';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?=e($round['event_name'])?> · Final Ranking &amp; Points · <?=e($reportStatus)?></title>
<style>
@page{size:A4 portrait;margin:9mm}
*{box-sizing:border-box}
html,body{margin:0;padding:0}
body{font-family:Arial,Helvetica,sans-serif;color:#111;background:#eceff2}
.toolbar{position:sticky;top:0;z-index:5;padding:9px;background:#fff;border-bottom:1px solid #ccc;text-align:right}
.page{
 width:192mm;
 min-height:279mm;
 margin:7mm auto;
 padding:8mm;
 background:#fff;
 display:flex;
 flex-direction:column;
}
.header{
 display:grid;
 grid-template-columns:25mm 1fr 38mm;
 gap:4mm;
 align-items:start;
 border-bottom:2px solid #111;
 padding-bottom:4mm;
}
.logo{width:24mm;height:24mm;object-fit:contain}
.title{text-align:center}
.title h1{margin:0;font-size:17pt;line-height:1.05}
.title h2{margin:2mm 0 0;font-size:10.5pt;font-weight:600}
.meta{text-align:right;font-size:8pt;line-height:1.4}
.round-title{
 margin:5mm 0 3mm;
 text-align:center;
 font-size:12pt;
 font-weight:800;
 font-style:italic;
 text-transform:uppercase;
}
table{
 width:100%;
 border-collapse:collapse;
 table-layout:fixed;
 font-size:8.5pt;
}
th,td{
 border:1px solid #777;
 padding:1.8mm 1.4mm;
 vertical-align:middle;
}
th{
 background:#f1f1f1;
 font-size:8.5pt;
 text-transform:uppercase;
}
.rank{width:13mm;text-align:center;font-weight:800}
.bib{width:14mm;text-align:center;color:#555}
.name{font-size:9pt;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.points{width:28mm;text-align:center;font-size:9.5pt;font-weight:800}
.blank-row td{height:8mm}
.info-grid{
 display:grid;
 grid-template-columns:1.1fr .9fr;
 gap:5mm;
 margin-top:5mm;
}
.box{
 border:1px solid #888;
 padding:3mm;
 min-height:25mm;
 font-size:8pt;
}
.box h3{
 margin:0 0 2mm;
 font-size:8.5pt;
 text-transform:uppercase;
}
.judges{
 line-height:1.5;
}
.officials{
 display:grid;
 grid-template-columns:1fr 1fr;
 gap:4mm;
}
.official-line{
 border-top:1px solid #111;
 padding-top:1.5mm;
 margin-top:8mm;
 font-size:8pt;
}
.note{
 margin-top:4mm;
 padding:2.5mm 3mm;
 border:1px solid #bbb;
 background:#fafafa;
 font-size:7.8pt;
 line-height:1.35;
}
.spacer{flex:1}
.footer{
 display:flex;
 justify-content:space-between;
 margin-top:4mm;
 padding-top:2mm;
 border-top:1px solid #999;
 font-size:7.5pt;
}
@media print{
 body{background:#fff}
 .toolbar{display:none}
 .page{width:auto;min-height:0;margin:0;padding:0}
}
</style>
</head>
<body>
<div class="toolbar"><button onclick="window.print()">Print / Save as PDF</button></div>

<section class="page">
 <header class="header">
  <img class="logo" src="<?=e($logo)?>" alt="BDC Logo">
  <div class="title">
   <h1><?=e($round['event_name'])?></h1>
   <h2>FINAL RANKING &amp; POINTS · <?=e(strtoupper($reportStatus))?></h2>
  </div>
  <div class="meta">
   <strong>Chief Judge:</strong> <?=e($chiefJudge?:'—')?><br>
   <strong>Judges:</strong> <?=count($judges)?><br>
   <strong>Date:</strong> <?=e(date('j M Y',strtotime((string)$round['event_date'])))?><br>
   <strong>Tier:</strong> <?=$tier?>
  </div>
 </header>

 <div class="round-title">
  <?=e(ucfirst($round['division']))?> Division · <?=e(strtoupper($reportStatus))?> · Tier <?=$tier?>
 </div>

 <table>
  <colgroup>
   <col style="width:13mm">
   <col style="width:14mm">
   <col>
   <col style="width:14mm">
   <col>
   <col style="width:28mm">
  </colgroup>
  <thead>
   <tr>
    <th>Rank</th>
    <th>Bib</th>
    <th>Leader</th>
    <th>Bib</th>
    <th>Follower</th>
    <th>Points Awarded Each</th>
   </tr>
  </thead>
  <tbody>
  <?php foreach($pairs as $pair):?>
   <tr>
    <td class="rank"><?=e(reportOrdinal((int)$pair['final_rank']))?></td>
    <td class="bib"><?=$pair['leader_bib']?></td>
    <td class="name"><?=e($pair['leader_name'])?></td>
    <td class="bib"><?=$pair['follower_bib']?></td>
    <td class="name"><?=e($pair['follower_name'])?></td>
    <td class="points"><?=number_format(reportPoints($tier,(int)$pair['final_rank'],$count),1)?></td>
   </tr>
  <?php endforeach;?>

  <?php for($blank=count($pairs);$blank<10;$blank++):?>
   <tr class="blank-row">
    <td></td><td></td><td></td><td></td><td></td><td></td>
   </tr>
  <?php endfor;?>
  </tbody>
 </table>

 <div class="info-grid">
  <div class="box">
   <h3>Judges</h3>
   <div class="judges"><?=e(implode(', ',$judgeNames))?></div>
  </div>

  <div class="box">
   <h3>Officials</h3>
   <div class="officials">
    <div>
     <div class="official-line">Chief Judge: <?=e($chiefJudge)?></div>
    </div>
    <div>
     <div class="official-line">Scoring Administrator: <?=e((string)($round['scoring_administrator']??''))?></div>
    </div>
   </div>
  </div>
 </div>

 <div class="note">
  Each Leader and Follower receives the listed BDC points. Publishing adds this Final result to the Result Repository and archives the scoring record.
 </div>

 <div class="spacer"></div>

 <footer class="footer">
  <span>Bachata Dance Council</span>
  <span>Official Final Ranking and Points Allocation</span>
 </footer>
</section>
</body>
</html>
