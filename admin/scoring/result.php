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
$isRepositorySnapshot=HtmlSnapshotToken::verify($pdo,'heats',$roundId,$_GET);
if(!$isRepositorySnapshot){Auth::requireAdmin();}
if(!PdfExportToken::verify($pdo,'heats',$roundId,$_GET)){Auth::requireAdmin();}
$roundStmt=$pdo->prepare("SELECT r.*,e.name AS event_name,e.event_date,e.venue FROM bdc_scoring_rounds r JOIN bdc_events e ON e.id=r.event_id WHERE r.id=:id");
$roundStmt->execute(['id'=>$roundId]);
$round=$roundStmt->fetch();
if(!$round){http_response_code(404);exit('Round not found.');}

$judgeStmt=$pdo->prepare("SELECT * FROM bdc_scoring_judges WHERE round_id=:r ORDER BY judge_order");
$judgeStmt->execute(['r'=>$roundId]);
$judges=$judgeStmt->fetchAll();

$entryStmt=$pdo->prepare("
 SELECT se.*,sr.total_score,sr.chief_score,sr.rank_number,sr.result_status,sr.alternate_rank
 FROM bdc_scoring_entries se
 LEFT JOIN bdc_scoring_results sr ON sr.round_id=se.round_id AND sr.entry_id=se.id
 WHERE se.round_id=:r AND se.entry_status='active'
 ORDER BY se.dance_role,
          CASE WHEN sr.rank_number IS NULL THEN 999999 ELSE sr.rank_number END,
          sr.total_score DESC,
          se.bib_number
");
$entryStmt->execute(['r'=>$roundId]);
$judgeCount=count($judges);
$fitAll=(string)($_GET['layout']??'')==='fit';
$summaryOnly=$judgeCount>30&&!$fitAll;
$pageSize='A4 landscape';
$entries=['leader'=>[],'follower'=>[]];
foreach($entryStmt->fetchAll() as $entry)$entries[$entry['dance_role']][]=$entry;
$largestRoleCount=max(count($entries['leader']),count($entries['follower']));
$pageSize=(!$summaryOnly && $judgeCount<=7 && $largestRoleCount<=20)?'A4 portrait':'A4 landscape';
$singleColumn=$pageSize==='A4 portrait';


$markStmt=$pdo->prepare("SELECT entry_id,judge_id,mark_type,alt_rank,weighted_score FROM bdc_scoring_marks WHERE round_id=:r");
$markStmt->execute(['r'=>$roundId]);
$marks=[];
foreach($markStmt->fetchAll() as $mark)$marks[(int)$mark['entry_id']][(int)$mark['judge_id']]=$mark;

function markLabel(?array $mark,bool $automatic=false):string{
 if($automatic)return $mark===null?'':number_format((float)$mark['weighted_score'],2);
 if(!$mark||$mark['mark_type']==='blank')return '';
 if($mark['mark_type']==='yes')return '1';
 if($mark['mark_type']==='alt')return 'A'.(int)$mark['alt_rank'];
 return '';
}
function resultLabel(array $entry):string{
 $status=(string)($entry['result_status']??'');
 if($status==='callback')return 'CB #'.(int)$entry['rank_number'];
 if($status==='alternate')return 'ALT '.(int)$entry['alternate_rank'];
 if($status==='tie_pending')return 'TIE #'.(int)$entry['rank_number'];
 if($status==='eliminated')return '—';
 return '';
}
$logo=url('public/assets/img/bdc-logo-header.png');
$isAutomatic=($round['scoring_mode']??'manual')==='automated';
$witnesses=array_values(array_filter([
 trim((string)($round['witness_1']??'')),
 trim((string)($round['witness_2']??'')),
 trim((string)($round['witness_3']??''))
]));

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
<title><?=e($round['event_name'])?> · <?=e(ucfirst($round['round_type']))?> · <?=e($reportStatus)?></title>
<style>
@page{size:<?=$fitAll?'A3 landscape':$pageSize?>;margin:7mm}
*{box-sizing:border-box}
body{margin:0;background:#eceff2;color:#111;font-family:Arial,Helvetica,sans-serif}
.toolbar{position:sticky;top:0;z-index:5;padding:10px;background:#fff;border-bottom:1px solid #ccc;text-align:right}
.page{width:<?=$singleColumn?'196mm':'283mm'?>;min-height:196mm;margin:8mm auto;padding:7mm;background:#fff;page-break-after:always}
.page:last-child{page-break-after:auto}
.header{display:grid;grid-template-columns:30mm 1fr 45mm;gap:6mm;align-items:start;border-bottom:2px solid #111;padding-bottom:3mm}
.logo{width:27mm;height:27mm;object-fit:contain}
.title{text-align:center}
.title h1{margin:0;font-size:16pt}
.title h2{margin:2mm 0 0;font-size:11pt;text-transform:uppercase}
.meta{text-align:right;font-size:8.5pt;line-height:1.45}
.tables{display:grid;grid-template-columns:<?=$singleColumn?'1fr':'1fr 1fr'?>;gap:5mm;margin-top:5mm}
.role h3{margin:0 0 2mm;font-size:10pt;text-transform:uppercase}
table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:7.6pt}
th,td{border:1px solid #111;padding:1.2mm .8mm;text-align:center;height:6mm}
th{background:#eee;font-weight:700}
th.name,td.name{text-align:left;width:35mm}
th.bib,td.bib{width:12mm}
th.total,td.total{width:15mm;font-weight:700}
th.result,td.result{width:16mm;font-weight:700}
.callback{background:#dff4e6}
.alternate{background:#fff2c7}
.tie_pending{background:#f8d7da}
.eliminated{color:#666}
.judge-key{display:flex;flex-wrap:wrap;gap:2mm 5mm;margin-top:4mm;padding:3mm;border:1px solid #bbb;font-size:8pt}.judge-key strong{width:100%}.judge-key span{white-space:nowrap}.footer{display:grid;grid-template-columns:1fr 55mm;gap:8mm;margin-top:5mm;padding-top:3mm;border-top:1px solid #111;font-size:8.5pt}
.witnesses strong{display:block;margin-bottom:2mm}
.witness-line{display:inline-block;min-width:48mm;margin:0 5mm 3mm 0;border-bottom:1px solid #111;padding-bottom:1mm}
.version{text-align:right}
.fit-shell{overflow-x:auto;padding:8mm}.fit-page{width:max-content;min-width:400mm}.fit-table{width:max-content;min-width:100%;font-size:<?=max(4.2,7.2-min(3.0,max(0,$judgeCount-12)*0.12))?>pt}.fit-table th,.fit-table td{min-width:9mm;padding:.8mm .5mm;height:5mm}.fit-table th.name,.fit-table td.name{min-width:42mm}.fit-table th.bib,.fit-table td.bib{min-width:11mm}.fit-table th.total,.fit-table td.total{min-width:14mm}.fit-table th.result,.fit-table td.result{min-width:16mm}
@media print{
 body{background:#fff}
 .toolbar{display:none}
 .page{width:auto;min-height:0;margin:0;padding:0}
 .fit-shell{overflow:visible;padding:0}.fit-page{width:auto;min-width:0}
}
</style>
</head>
<body>
<div class="toolbar"><?php if($summaryOnly):?><a href="audit.php?round_id=<?=$roundId?>" style="margin-right:10px">View Judge Audit</a><?php endif;?><a href="?round_id=<?=$roundId?>" style="margin-right:10px">Readable Pages</a><a href="?round_id=<?=$roundId?>&amp;layout=fit" style="margin-right:10px">Landscape, All Judges</a><button onclick="window.print()">Print / Save as PDF</button></div>
<?php if($fitAll):?>
<?php foreach(['leader'=>'Leaders','follower'=>'Followers'] as $role=>$label):?>
<div class="fit-shell"><div class="page fit-page">
 <header class="header"><img class="logo" src="<?=e($logo)?>" alt="BDC Logo"><div class="title"><h1><?=e($round['event_name'])?></h1><h2><?=e(strtoupper($round['round_type']))?> · <?=e(strtoupper($reportStatus))?> · <?=e(strtoupper($label))?></h2></div><div class="meta"><strong>Judges:</strong> <?=count($judges)?><br><strong>Date:</strong> <?=e(date('j M Y',strtotime((string)$round['event_date'])))?></div></header>
 <table class="fit-table"><thead><tr><th class="bib">Bib</th><th class="name">Competitor</th><?php foreach($judges as $judge):?><th>J<?=(int)$judge['judge_order']?><?=(int)$judge['is_chief']?'★':''?></th><?php endforeach;?><th class="total"><?=$isAutomatic?'Average':'Total'?></th><th class="result">Result</th></tr></thead><tbody><?php foreach($entries[$role] as $entry):?><tr class="<?=e((string)($entry['result_status']??''))?>"><td class="bib"><?=(int)$entry['bib_number']?></td><td class="name"><?=e($entry['display_name'])?></td><?php foreach($judges as $judge):?><td><?=e(markLabel($marks[(int)$entry['id']][(int)$judge['id']]??null,$isAutomatic))?></td><?php endforeach;?><td class="total"><?=number_format((float)($entry['total_score']??0),1)?></td><td class="result"><?=e(resultLabel($entry))?></td></tr><?php endforeach;?></tbody></table>
 <div class="judge-key"><strong>Judge Key</strong><?php foreach($judges as $judge):?><span><b>J<?=(int)$judge['judge_order']?></b> · <?=e($judge['judge_name'])?><?=(int)$judge['is_chief']?' ★ Chief Judge':''?></span><?php endforeach;?></div>
</div></div>
<?php endforeach;?>
<?php else:?>
<div class="page">
 <header class="header">
  <img class="logo" src="<?=e($logo)?>" alt="BDC Logo">
  <div class="title">
   <h1><?=e($round['event_name'])?></h1>
   <h2><?=e(strtoupper($round['round_type']))?> · <?=e(strtoupper($reportStatus))?></h2>
  </div>
  <div class="meta">
   <strong>Chief Judge:</strong> <?=e($chiefJudge?:'—')?><br>
   <strong>Judges:</strong> <?=count($judges)?><br>
   <strong>Date:</strong> <?=e(date('j M Y',strtotime((string)$round['event_date'])))?>
  </div>
 </header>

 <div class="tables">
 <?php foreach(['leader'=>'Leaders','follower'=>'Followers'] as $role=>$label):?>
  <section class="role">
   <h3><?=$label?></h3>
   <table>
    <thead><tr>
     <th class="bib">Bib</th>
     <th class="name">Competitor</th>
     <?php if(!$summaryOnly):foreach($judges as $judge):?><th>J<?= (int)$judge['judge_order'] ?><?=(int)$judge['is_chief']?'★':''?></th><?php endforeach;endif;?>
     <th class="total"><?=$isAutomatic?'Average':'Total'?></th>
     <th class="result">Result</th>
    </tr></thead>
    <tbody>
    <?php foreach($entries[$role] as $entry):?>
     <tr class="<?=e((string)($entry['result_status']??''))?>">
      <td class="bib"><?= (int)$entry['bib_number'] ?></td>
      <td class="name"><?=e($entry['display_name'])?></td>
      <?php if(!$summaryOnly):foreach($judges as $judge):?><td><?=e(markLabel($marks[(int)$entry['id']][(int)$judge['id']]??null,$isAutomatic))?></td><?php endforeach;endif;?>
      <td class="total"><?=number_format((float)($entry['total_score']??0),1)?></td>
      <td class="result"><?=e(resultLabel($entry))?></td>
     </tr>
    <?php endforeach;?>
    </tbody>
   </table>
  </section>
 <?php endforeach;?>
 </div>

 <?php if(!$summaryOnly):?><div class="judge-key">
  <strong>Judge Key</strong>
  <?php foreach($judges as $judgeIndex=>$judge):?>
   <span><b>J<?=$judgeIndex+1?></b> · <?=e($judge['judge_name'])?><?=(int)$judge['is_chief']?' ★ Chief Judge':''?></span>
  <?php endforeach;?>
 </div><?php else:?><div class="judge-key"><strong>Summary Report</strong><span>Detailed marks are available through View Judge Audit.</span></div><?php endif;?>

 <footer class="footer">
  <div class="witnesses">
   <strong>Scoring Witnesses</strong>
   <?php if($witnesses):foreach($witnesses as $witness):?><span class="witness-line"><?=e($witness)?></span><?php endforeach;else:?>
   <span class="witness-line">&nbsp;</span><span class="witness-line">&nbsp;</span><span class="witness-line">&nbsp;</span>
   <?php endif;?>
  </div>
  <div class="version">
   <strong>Chief Judge:</strong><br><?=e((string)($judges[array_search(1,array_map('intval',array_column($judges,'is_chief')) )]['judge_name']??''))?><br><br>
   <strong>Scoring Administrator:</strong><br><?=e((string)($round['scoring_administrator']??''))?>
  </div>
 </footer>
</div>
<?php endif;?>
</body>
</html>
