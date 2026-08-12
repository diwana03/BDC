<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Services\SchemaUpdater;
use App\Services\HtmlSnapshotToken;
use App\Services\PdfExportToken;

$pdo=Database::connection();


$roundId=(int)($_GET['round_id']??0);
$isRepositorySnapshot=HtmlSnapshotToken::verify($pdo,'finals',$roundId,$_GET);
if(!$isRepositorySnapshot){Auth::requireAdmin();}
if(!PdfExportToken::verify($pdo,'final',$roundId,$_GET)){Auth::requireAdmin();}

$roundStmt=$pdo->prepare("
 SELECT r.*,e.name AS event_name,e.event_date,e.venue
 FROM bdc_test_scoring_rounds r
 JOIN bdc_test_events e ON e.id=r.event_id
 WHERE r.id=:id AND r.round_type='final'
");
$roundStmt->execute(['id'=>$roundId]);
$round=$roundStmt->fetch();
if(!$round){http_response_code(404);exit('Final round not found.');}

$judgeStmt=$pdo->prepare("SELECT * FROM bdc_test_scoring_judges WHERE round_id=:r ORDER BY judge_order");
$judgeStmt->execute(['r'=>$roundId]);
$judges=$judgeStmt->fetchAll();

$pairStmt=$pdo->prepare("
 SELECT fp.id,fp.pair_number,
        le.display_name leader_name,le.bib_number leader_bib,
        fe.display_name follower_name,fe.bib_number follower_bib,
        fr.final_rank,fr.majority_level,fr.majority_count,fr.placement_sum,fr.chief_rank,fr.decision_json
 FROM bdc_test_scoring_final_pairs fp
 JOIN bdc_test_scoring_entries le ON le.id=fp.leader_entry_id
 LEFT JOIN bdc_test_scoring_entries fe ON fe.id=fp.follower_entry_id
 LEFT JOIN bdc_test_scoring_final_results fr ON fr.round_id=fp.round_id AND fr.pair_id=fp.id
 WHERE fp.round_id=:r AND fp.pairing_status='confirmed'
 ORDER BY COALESCE(fr.final_rank,999999),fp.pair_number
");
$pairStmt->execute(['r'=>$roundId]);
$pairs=$pairStmt->fetchAll();
if(!$pairs)exit('No confirmed Final pairs found.');

$markStmt=$pdo->prepare("SELECT pair_id,judge_id,rank_value FROM bdc_test_scoring_final_marks WHERE round_id=:r");
$markStmt->execute(['r'=>$roundId]);
$marks=[];
foreach($markStmt->fetchAll() as $mark)$marks[(int)$mark['pair_id']][(int)$mark['judge_id']]=(int)$mark['rank_value'];

$logo=url('public/assets/img/bdc-logo-header.png');
$pairCount=count($pairs);
$judgeCount=count($judges);
$fitAll=(string)($_GET['layout']??'')==='fit';
$largeJudgePanel=$judgeCount>15&&!$fitAll;
$majority=(int)floor($judgeCount/2)+1;
$witnesses=[
 trim((string)($round['witness_1']??'')),
 trim((string)($round['witness_2']??'')),
 trim((string)($round['witness_3']??''))
];


function decisionExplanation(array $pair,int $judgeCount):array{
 $decision=json_decode((string)($pair['decision_json']??''),true);
 $level=(int)($pair['majority_level']??0);
 $count=(int)($pair['majority_count']??0);
 $sum=(int)($pair['placement_sum']??0);
 $step=(string)($decision['deciding_step']??'');
 $lines=[
  'Majority achieved in Top '.$level.'.',
  $count.' of '.$judgeCount.' judges ranked this couple in the Top '.$level.'.',
  'Majority placement sum: '.$sum.'.'
 ];
 if($step==='count'){
  $lines[]='Won the comparison because more judges ranked this couple within the next expanded placement level.';
 }elseif($step==='sum'){
  $lines[]='Won the comparison because it had the lower placement sum at the deciding level.';
 }elseif($step==='chief_judge'){
  $lines[]='All Relative Placement comparisons remained tied, so the Chief Judge ranking decided the order.';
 }elseif($step==='total_sum'){
  $lines[]='All cumulative comparisons and Chief Judge ranking remained tied, so the lower total rank sum decided the order.';
 }else{
  $lines[]='Won by reaching the required majority at the earliest placement level.';
 }
 return $lines;
}

function ordinal(int $number):string{
 if($number%100>=11&&$number%100<=13)return $number.'th';
 return $number.([1=>'st',2=>'nd',3=>'rd'][$number%10]??'th');
}

$reportStatus=$isRepositorySnapshot?'Official Result':'Draft Result';
$chiefJudge='';
foreach($judges as $judge){
 if((int)$judge['is_chief']===1){
  $chiefJudge=(string)$judge['judge_name'];
  break;
 }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?=e($round['event_name'])?> · Final · <?=e($reportStatus)?></title>
<style>
@page{size:<?=$fitAll?'A3 landscape':'A4 landscape'?>;margin:7mm}
*{box-sizing:border-box}
body{margin:0;background:#eef1f4;color:#171717;font-family:Arial,Helvetica,sans-serif}
.toolbar{position:sticky;top:0;z-index:5;padding:10px;background:#fff;border-bottom:1px solid #ccc;text-align:right}
.page{width:283mm;min-height:196mm;margin:7mm auto;padding:7mm;background:#fff}
.header{display:grid;grid-template-columns:25mm 1fr 45mm;gap:5mm;align-items:start;border-bottom:2px solid #111;padding-bottom:3mm}
.logo{width:24mm;height:24mm;object-fit:contain}
.title{text-align:center}
.title h1{margin:0;font-size:18pt}
.title h2{margin:2mm 0 0;font-size:12pt;text-transform:uppercase}
.meta{text-align:right;font-size:8.5pt;line-height:1.5}
.details{display:grid;grid-template-columns:1fr 1fr;gap:3mm;margin:4mm 0;font-size:9pt}
.detail{padding:2mm;border:1px solid #d5d9de;border-radius:2mm;background:#fafafa}
.final-ranking{margin-bottom:5mm}
.final-ranking h3,.panel h3{margin:0 0 2.5mm;font-size:11pt}
.rank-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:3mm}
.rank-card{display:grid;grid-template-columns:14mm 1fr;align-items:center;gap:3mm;padding:3mm;border:1px solid #cfd4da;border-radius:2mm;background:#fff}
.rank-number{font-size:18pt;font-weight:800;text-align:center}
.rank-names{font-size:12pt;font-weight:800;line-height:1.35}
.rank-bibs{margin-top:1mm;color:#666;font-size:8pt}
.rank-1{border-left:4px solid #c79a17}.rank-2{border-left:4px solid #8b939c}.rank-3{border-left:4px solid #a9683a}
.split{display:block}.split .panel{margin-bottom:5mm}
.panel{border:1px solid #cfd4da;border-radius:2mm;padding:3mm;background:#fff;overflow:hidden}
table{width:100%;border-collapse:collapse;table-layout:auto;font-size:8pt}
th,td{border:1px solid #bfc5cc;padding:1.4mm;text-align:center}
th{background:#eef1f4}
.name-cell{text-align:left;font-size:9.2pt;font-weight:700;line-height:1.25;white-space:nowrap;width:45%}
.judge-key{display:flex;flex-wrap:wrap;gap:2mm 5mm;margin-top:3mm;padding:2.5mm;border:1px solid #d8dde3;border-radius:2mm;background:#fafafa;font-size:8pt}
.judge-key strong{width:100%}
.judge-key span{white-space:nowrap}
.summary-list{margin:0;padding:0;list-style:none}
.summary-list li{margin-bottom:2.5mm;padding:2.5mm;border:1px solid #d8dde3;border-radius:2mm}
.summary-list strong{font-size:9pt}
.summary-list span{display:block;margin-top:1mm;font-size:8pt;line-height:1.45}
.witness-panel{display:grid;grid-template-columns:1fr 1fr;gap:5mm;margin-top:5mm}
.signature-box{border:1px solid #cfd4da;border-radius:2mm;padding:3mm;min-height:28mm}
.signature-box h3{margin:0 0 4mm;font-size:10pt}
.signature-line{display:inline-block;min-width:52mm;margin:0 4mm 5mm 0;border-bottom:1px solid #111;padding-bottom:1mm;font-size:8.5pt}
.footer{display:flex;justify-content:space-between;margin-top:4mm;padding-top:2mm;border-top:1px solid #cfd4da;font-size:8pt}
.fit-all .page{width:max-content;min-width:400mm}.fit-all .judge-ranking-table{font-size:<?=max(4.2,7.2-min(3.0,max(0,$judgeCount-12)*0.12))?>pt}.fit-all .judge-ranking-table th,.fit-all .judge-ranking-table td{min-width:9mm;padding:.8mm .5mm}.fit-all .judge-ranking-table .name-cell{min-width:48mm;width:auto}
@media print{body{background:#fff}.toolbar{display:none}.page{width:auto;min-height:0;margin:0;padding:0}}
</style>
</head>
<body class="<?=$fitAll?'fit-all':''?>">
<div class="toolbar"><?php if($largeJudgePanel):?><a href="final-audit.php?round_id=<?=$roundId?>" style="margin-right:10px">View Final Judge Audit</a><?php endif;?><a href="?round_id=<?=$roundId?>" style="margin-right:10px">Readable Pages</a><a href="?round_id=<?=$roundId?>&amp;layout=fit" style="margin-right:10px">Landscape, All Judges</a><button onclick="window.print()">Print / Save as PDF</button></div>
<section class="page">
 <header class="header">
  <img class="logo" src="<?=e($logo)?>" alt="BDC Logo">
  <div class="title">
   <h1><?=e($round['event_name'])?></h1>
   <h2>FINAL · <?=e(strtoupper($reportStatus))?></h2>
  </div>
  <div class="meta">
   <strong>Chief Judge:</strong> <?=e($chiefJudge?:'—')?><br>
   <strong>Judges:</strong> <?=$judgeCount?><br>
   <strong>Date:</strong> <?=e(date('j M Y',strtotime((string)$round['event_date'])))?><br>
   <strong>Couples:</strong> <?=$pairCount?>
  </div>
 </header>

 <div class="details">
  <div class="detail"><strong>Division</strong><br><?=e(ucfirst($round['division']))?></div>
  <div class="detail"><strong>Relative Placement Majority</strong><br><?=$majority?> of <?=$judgeCount?> judges</div>
 </div>

 <section class="final-ranking">
  <h3>Final Ranking</h3>
  <div class="rank-grid">
  <?php foreach($pairs as $pair):$rank=(int)($pair['final_rank']??0);?>
   <div class="rank-card rank-<?=$rank?>">
    <div class="rank-number"><?=$rank?ordinal($rank):'—'?></div>
    <div>
     <div class="rank-names"><?=e($pair['leader_name'])?> &amp; <?=e((string)$pair['follower_name'])?></div>
     <div class="rank-bibs">Couple <?=$pair['pair_number']?> · Bib <?=$pair['leader_bib']?> &amp; <?=$pair['follower_bib']?></div>
    </div>
   </div>
  <?php endforeach;?>
  </div>
 </section>

 <div class="split">
  <?php if(!$largeJudgePanel):?>
  <section class="panel">
   <h3>Judge Rankings</h3>
   <table class="judge-ranking-table">
    <colgroup><col style="width:9%"><col style="width:9%"><col style="width:42%"><?php foreach($judges as $judge):?><col><?php endforeach;?></colgroup>
    <thead><tr><th>Rank</th><th>Couple</th><th class="name-cell">Contestants</th><?php foreach($judges as $judgeIndex=>$judge):?><th>J<?=$judgeIndex+1?><?=(int)$judge['is_chief']?' ★':''?></th><?php endforeach;?></tr></thead>
    <tbody>
    <?php foreach($pairs as $pair):?>
     <tr>
      <td><strong><?=ordinal((int)$pair['final_rank'])?></strong></td>
      <td><?=$pair['pair_number']?></td>
      <td class="name-cell"><?=e($pair['leader_name'])?> &amp; <?=e((string)$pair['follower_name'])?></td>
      <?php foreach($judges as $judge):?><td><?=$marks[(int)$pair['id']][(int)$judge['id']]??'—'?></td><?php endforeach;?>
     </tr>
    <?php endforeach;?>
    </tbody>
   </table>
   <div class="judge-key">
    <strong>Judge Key</strong>
    <?php foreach($judges as $judgeIndex=>$judge):?>
     <span><b>J<?=$judgeIndex+1?></b> · <?=e($judge['judge_name'])?><?=(int)$judge['is_chief']?' ★ Chief Judge':''?></span>
    <?php endforeach;?>
   </div>
  </section>

  <?php else:?><section class="panel"><h3>Large Judge Panel</h3><p>Detailed judge rankings are available through <strong>View Final Judge Audit</strong>. The Final Ranking and Relative Placement summary remain below.</p></section><?php endif;?>
  <section class="panel">
   <h3>Relative Placement Counts</h3>
   <table class="placement-count-table">
    <colgroup><col style="width:12%"><col style="width:12%"><?php for($level=1;$level<=$pairCount;$level++):?><col><?php endfor;?></colgroup>
    <thead><tr><th>Rank</th><th>Couple</th><?php for($level=1;$level<=$pairCount;$level++):?><th>Top <?=$level?></th><?php endfor;?></tr></thead>
    <tbody>
    <?php foreach($pairs as $pair):?>
     <tr>
      <td><strong><?=ordinal((int)$pair['final_rank'])?></strong></td>
      <td><?=$pair['pair_number']?></td>
      <?php for($level=1;$level<=$pairCount;$level++):
       $count=0;
       foreach($judges as $judge){
        $rank=$marks[(int)$pair['id']][(int)$judge['id']]??0;
        if($rank>0&&$rank<=$level)$count++;
       }
      ?>
       <td><?=$count?></td>
      <?php endfor;?>
     </tr>
    <?php endforeach;?>
    </tbody>
   </table>
  </section>
 </div>

 <section class="panel" style="margin-top:5mm">
  <h3>Relative Placement Summary</h3>
  <ul class="summary-list">
  <?php foreach($pairs as $pair):?>
   <li>
    <strong><?=ordinal((int)$pair['final_rank'])?> · <?=e($pair['leader_name'])?> &amp; <?=e((string)$pair['follower_name'])?></strong>
    <?php foreach(decisionExplanation($pair,$judgeCount) as $line):?>
     <span>✓ <?=e($line)?></span>
    <?php endforeach;?>
   </li>
  <?php endforeach;?>
  </ul>
 </section>

 <div class="witness-panel">
  <div class="signature-box">
   <h3>Scoring Witnesses</h3>
   <span class="signature-line">Witness 1: <?=e($witnesses[0])?></span>
   <span class="signature-line">Witness 2: <?=e($witnesses[1])?></span>
   <span class="signature-line">Witness 3: <?=e($witnesses[2])?></span>
  </div>
  <div class="signature-box">
   <h3>Officials</h3>
   <span class="signature-line">Chief Judge: <?=e($chiefJudge)?></span>
   <span class="signature-line">Scoring Administrator: <?=e((string)($round['scoring_administrator']??''))?></span>
  </div>
 </div>

 <footer class="footer">
  <span>Bachata Dance Council</span>
  <span>Final Relative Placement result</span>
 </footer>
</section>
</body>
</html>
