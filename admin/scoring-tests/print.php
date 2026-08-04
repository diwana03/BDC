<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Services\SchemaUpdater;

Auth::requireAdmin();
$pdo=Database::connection();


$roundId=(int)($_GET['round_id']??0);

$roundStmt=$pdo->prepare("
 SELECT r.*,e.name AS event_name,e.event_date,e.venue
 FROM bdc_test_scoring_rounds r
 JOIN bdc_test_events e ON e.id=r.event_id
 WHERE r.id=:id
");
$roundStmt->execute(['id'=>$roundId]);
$round=$roundStmt->fetch();

if(!$round){
 http_response_code(404);
 exit('Round not found.');
}

$judgeStmt=$pdo->prepare("
 SELECT *
 FROM bdc_test_scoring_judges
 WHERE round_id=:r
 ORDER BY judge_order
");
$judgeStmt->execute(['r'=>$roundId]);
$judges=$judgeStmt->fetchAll();

if(!$judges){
 exit('Add and save judges before printing judge sheets.');
}

$logo=url('public/assets/img/bdc-logo-header.png');

if($round['round_type']==='final'){
 $pairStmt=$pdo->prepare("
  SELECT
   fp.*,
   le.display_name AS leader_name,
   le.bib_number AS leader_bib,
   fe.display_name AS follower_name,
   fe.bib_number AS follower_bib
  FROM bdc_test_scoring_final_pairs fp
  JOIN bdc_test_scoring_entries le ON le.id=fp.leader_entry_id
  LEFT JOIN bdc_test_scoring_entries fe ON fe.id=fp.follower_entry_id
  WHERE fp.round_id=:r
    AND fp.pairing_status='confirmed'
  ORDER BY fp.pair_number
 ");
 $pairStmt->execute(['r'=>$roundId]);
 $pairs=$pairStmt->fetchAll();

 if(!$pairs){
  exit('Confirm Final pairing before printing Final judge sheets.');
 }

 // Keep each Final page clean. More than 8 couples continues on another A4 page for the same judge.
 $chunks=array_chunk($pairs,8);
 ?>
 <!doctype html>
 <html lang="en">
 <head>
 <meta charset="utf-8">
 <title>BDC Final Judge Sheets</title>
 <style>
 @page{size:A4 portrait;margin:9mm}
 *{box-sizing:border-box}
 html,body{margin:0;padding:0}
 body{font-family:Arial,Helvetica,sans-serif;color:#111;background:#eceff2}
 .toolbar{position:sticky;top:0;z-index:10;padding:10px;background:#fff;border-bottom:1px solid #ccc;text-align:right}
 .sheet{width:192mm;min-height:279mm;margin:8mm auto;padding:8mm;background:#fff;page-break-after:always;display:flex;flex-direction:column}
 .sheet:last-child{page-break-after:auto}
 .header{display:grid;grid-template-columns:28mm 1fr 37mm;gap:5mm;align-items:start;border-bottom:2px solid #111;padding-bottom:4mm}
 .logo{width:26mm;height:26mm;object-fit:contain}
 .title{text-align:center}
 .title h1{margin:0;font-size:17pt}
 .title h2{margin:2mm 0 0;font-size:12pt;text-transform:uppercase}
 .judge-meta{text-align:right;font-size:8.5pt;line-height:1.45}
 .details{display:grid;grid-template-columns:1fr 1fr;gap:2mm 7mm;margin-top:4mm;font-size:9pt}
 .detail{border-bottom:1px solid #555;padding:1.5mm 0;min-height:6mm}
 .instructions{margin:4mm 0;padding:3mm;border:1px solid #111;background:#f7f7f7;font-size:9.5pt;font-weight:700}
 .couple{margin-bottom:3.5mm;border:1.5px solid #111;page-break-inside:avoid}
 .couple-head{display:grid;grid-template-columns:1fr 25mm 1.6fr;border-bottom:1.5px solid #111;background:#eee;font-size:9.5pt;font-weight:700;text-align:center}
 .couple-head>div{padding:2mm;border-right:1px solid #111}
 .couple-head>div:last-child{border-right:0}
 .couple-body{display:grid;grid-template-columns:1fr 25mm 1.6fr;min-height:16mm}
 .names{padding:2mm;border-right:1px solid #111;font-size:9pt;line-height:1.5}
 .rank{display:flex;align-items:center;justify-content:center;border-right:1px solid #111;font-size:16pt;font-weight:700}
 .remarks{padding:2mm}
 .spacer{flex:1}
 .footer{display:grid;grid-template-columns:1fr 1fr 40mm;gap:7mm;margin-top:4mm;padding-top:3mm;border-top:1px solid #111;font-size:8pt}
 .signature{margin-top:6mm;border-top:1px solid #111;padding-top:1mm}
 .page-number{text-align:right}
 @media print{
  body{background:#fff}
  .toolbar{display:none}
  .sheet{width:auto;min-height:0;margin:0;padding:0}
 }
 </style>
 </head>
 <body>
 <div class="toolbar"><button onclick="window.print()">Print All Final Judge Sheets</button></div>

 <?php foreach($judges as $judgeIndex=>$judge):?>
  <?php foreach($chunks as $chunkIndex=>$pairChunk):?>
  <section class="sheet">
   <header class="header">
    <img class="logo" src="<?=e($logo)?>" alt="BDC Logo">
    <div class="title">
     <h1>BDC Jack &amp; Jill Judging Sheet</h1>
     <h2><?=e($round['division'])?> · Final Round</h2>
    </div>
    <div class="judge-meta">
     Judge <?=($judgeIndex+1)?> of <?=count($judges)?><br>
     <?=((int)$judge['is_chief']===1)?'<strong>CHIEF JUDGE</strong>':'Final Judge'?>
     <?php if(count($chunks)>1):?><br>Sheet <?=($chunkIndex+1)?> of <?=count($chunks)?><?php endif;?>
    </div>
   </header>

   <div class="details">
    <div class="detail"><strong>Event:</strong> <?=e($round['event_name'])?></div>
    <div class="detail"><strong>Date:</strong> <?=e((string)$round['event_date'])?></div>
    <div class="detail"><strong>Venue:</strong> <?=e((string)($round['venue']??''))?></div>
    <div class="detail"><strong>Judge:</strong> <?=e($judge['judge_name'])?></div>
   </div>

   <div class="instructions">
    Rank every fixed couple from 1 to <?=count($pairs)?>. Use each rank once only. Do not give two couples the same rank.
   </div>

   <?php foreach($pairChunk as $pair):?>
   <div class="couple">
    <div class="couple-head">
     <div>COUPLE <?=$pair['pair_number']?></div>
     <div>RANK</div>
     <div>REMARKS</div>
    </div>
    <div class="couple-body">
     <div class="names">
      <strong>LEAD:</strong> Bib <?=$pair['leader_bib']?> · <?=e($pair['leader_name'])?><br>
      <strong>FOLLOW:</strong> Bib <?=$pair['follower_bib']?> · <?=e((string)$pair['follower_name'])?>
     </div>
     <div class="rank"></div>
     <div class="remarks"></div>
    </div>
   </div>
   <?php endforeach;?>

   <div class="spacer"></div>

   <footer class="footer">
    <div><div class="signature">Judge Signature</div></div>
    <div><div class="signature">Witness / Scoring Admin</div></div>
    <div class="page-number">
     BDC Scoring Portal<br>
     Judge <?=($judgeIndex+1)?> · Page <?=($chunkIndex+1)?>
    </div>
   </footer>
  </section>
  <?php endforeach;?>
 <?php endforeach;?>
 </body>
 </html>
 <?php
 exit;
}

// Heats / Semifinal judge-sheet mode.
$entryStmt=$pdo->prepare("
 SELECT *
 FROM bdc_test_scoring_entries
 WHERE round_id=:r AND entry_status='active'
 ORDER BY dance_role,bib_number
");
$entryStmt->execute(['r'=>$roundId]);
$entries=['leader'=>[],'follower'=>[]];
foreach($entryStmt->fetchAll() as $entry){
 $entries[$entry['dance_role']][]=$entry;
}

function rosterColumns(array $entries):array{
 $count=count($entries);
 $columnCount=$count<=15?1:($count<=30?2:3);
 $rows=max(15,(int)ceil(max(1,$count)/$columnCount));
 $columns=array_fill(0,$columnCount,[]);
 foreach($entries as $index=>$entry){
  $column=min($columnCount-1,(int)floor($index/$rows));
  $columns[$column][]=$entry;
 }
 foreach($columns as &$column){
  while(count($column)<$rows)$column[]=null;
 }
 unset($column);
 return $columns;
}

$leaderColumns=rosterColumns($entries['leader']);
$followerColumns=rosterColumns($entries['follower']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>BDC <?=e(ucfirst($round['round_type']))?> Judge Sheets</title>
<style>
@page{size:A4 portrait;margin:8mm}
*{box-sizing:border-box}
html,body{margin:0;padding:0}
body{font-family:Arial,Helvetica,sans-serif;color:#111;background:#eceff2}
.toolbar{position:sticky;top:0;z-index:10;padding:10px;background:#fff;border-bottom:1px solid #ccc;text-align:right}
.sheet{width:194mm;min-height:281mm;margin:8mm auto;padding:7mm;background:#fff;page-break-after:always;display:flex;flex-direction:column}
.sheet:last-child{page-break-after:auto}
.header{display:grid;grid-template-columns:25mm 1fr 32mm;align-items:start;gap:5mm;border-bottom:2px solid #111;padding-bottom:3mm}
.logo{width:24mm;max-height:24mm;object-fit:contain}
.header-title{text-align:center}
.header-title h1{margin:0;font-size:16pt}
.header-title .round{margin-top:1.5mm;font-size:11pt;font-weight:700;text-transform:uppercase}
.page-meta{text-align:right;font-size:8.5pt;line-height:1.35}
.details{display:grid;grid-template-columns:1fr 1fr;gap:2mm 7mm;margin-top:3mm;font-size:9.5pt}
.detail{border-bottom:1px solid #555;padding:1.2mm 0;min-height:6mm}
.instructions{margin:3mm 0;padding:2.5mm 3mm;border:1px solid #111;background:#f7f7f7;font-size:9pt}
.role-block{margin-top:2mm}
.role-title{font-size:10pt;font-weight:700;text-transform:uppercase;margin-bottom:1mm}
.role-columns{display:grid;gap:2.5mm}
.cols-1{grid-template-columns:1fr}.cols-2{grid-template-columns:repeat(2,1fr)}.cols-3{grid-template-columns:repeat(3,1fr)}
table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:8.2pt}
th,td{border:1px solid #111;height:5.2mm;padding:.8mm 1mm}
th{background:#eee}
.bib{width:14mm;text-align:center;font-weight:700}.result{width:25mm;text-align:center}
.spacer{flex:1}
.footer{margin-top:3mm;padding-top:2mm;border-top:1px solid #111;display:grid;grid-template-columns:1fr 1fr 35mm;gap:6mm;font-size:8.5pt}
.signature{margin-top:5mm;border-top:1px solid #111;padding-top:1mm}
.page-number{text-align:right}
@media print{body{background:#fff}.toolbar{display:none}.sheet{width:auto;min-height:0;margin:0;padding:0}}
</style>
</head>
<body>
<div class="toolbar"><button onclick="window.print()">Print All Judge Sheets</button></div>
<?php foreach($judges as $judgeIndex=>$judge):?>
<section class="sheet">
 <header class="header">
  <img class="logo" src="<?=e($logo)?>" alt="BDC Logo">
  <div class="header-title">
   <h1>BDC Jack &amp; Jill Judging Sheet</h1>
   <div class="round"><?=e($round['division'])?> · <?=e($round['round_type'])?></div>
  </div>
  <div class="page-meta">Judge <?=($judgeIndex+1)?> of <?=count($judges)?><br><?=((int)$judge['is_chief']===1)?'<strong>CHIEF JUDGE</strong>':'Judge Sheet'?></div>
 </header>
 <div class="details">
  <div class="detail"><strong>Event:</strong> <?=e($round['event_name'])?></div>
  <div class="detail"><strong>Date:</strong> <?=e((string)$round['event_date'])?></div>
  <div class="detail"><strong>Venue:</strong> <?=e((string)($round['venue']??''))?></div>
  <div class="detail"><strong>Judge:</strong> <?=e($judge['judge_name'])?></div>
 </div>
 <div class="instructions">
  Select <strong><?=e((string)$round['yes_count'])?> YES</strong> for each role and rank <strong>3 alternates: A1, A2, A3</strong>.
 </div>
 <?php foreach([
  ['label'=>'Leaders','columns'=>$leaderColumns],
  ['label'=>'Followers','columns'=>$followerColumns]
 ] as $section):?>
 <div class="role-block">
  <div class="role-title"><?=e($section['label'])?></div>
  <div class="role-columns cols-<?=count($section['columns'])?>">
   <?php foreach($section['columns'] as $column):?>
   <table>
    <thead><tr><th class="bib">Bib</th><th class="result">YES / ALT</th><th>Notes</th></tr></thead>
    <tbody>
    <?php foreach($column as $entry):?>
     <tr><td class="bib"><?=$entry?e((string)$entry['bib_number']):''?></td><td></td><td></td></tr>
    <?php endforeach;?>
    </tbody>
   </table>
   <?php endforeach;?>
  </div>
 </div>
 <?php endforeach;?>
 <div class="spacer"></div>
 <footer class="footer">
  <div><div class="signature">Judge Signature</div></div>
  <div><div class="signature">Witness / Scoring Admin</div></div>
  <div class="page-number">BDC Scoring Portal<br>Page <?=($judgeIndex+1)?> / <?=count($judges)?></div>
 </footer>
</section>
<?php endforeach;?>
</body>
</html>
