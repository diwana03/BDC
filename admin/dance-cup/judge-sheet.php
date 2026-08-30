<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Services\DanceCupScoringService;

Auth::requireAdmin();
$pdo=Database::connection();
$test=(string)($_GET['data_mode']??'')==='test';
if($test&&!Auth::isSuperAdmin()){http_response_code(403);exit('Super Admin required.');}
DanceCupScoringService::ensureWorkspaceTables($pdo,$test);
$tables=DanceCupScoringService::tables($test);
$prefix=$test?'bdc_test_dance_cup':'bdc_dance_cup';
$competitionId=(int)($_GET['id']??0);

$query=$pdo->prepare("SELECT c.*,e.name event_name,e.event_date,e.end_date,e.venue,e.country event_country FROM {$tables['competitions']} c JOIN {$tables['events']} e ON e.id=c.event_id WHERE c.id=:id");
$query->execute(['id'=>$competitionId]);
$competition=$query->fetch();
if(!$competition){http_response_code(404);exit('Dance Cup category not found.');}

$query=$pdo->prepare("SELECT * FROM {$tables['criteria']} WHERE competition_id=:id ORDER BY sort_order,id");
$query->execute(['id'=>$competitionId]);
$criteria=$query->fetchAll();
$query=$pdo->prepare("SELECT * FROM {$prefix}_entries WHERE competition_id=:id AND status='active' ORDER BY bib_number,id");
$query->execute(['id'=>$competitionId]);
$entries=$query->fetchAll();
$query=$pdo->prepare("SELECT * FROM {$prefix}_judges WHERE competition_id=:id ORDER BY is_chief DESC,judge_order,id");
$query->execute(['id'=>$competitionId]);
$judges=$query->fetchAll();
$query=$pdo->prepare("SELECT entry_id,judge_id,criterion_id,points FROM {$prefix}_marks WHERE competition_id=:id");
$query->execute(['id'=>$competitionId]);
$marks=[];
foreach($query->fetchAll() as $mark)$marks[(int)$mark['judge_id']][(int)$mark['entry_id']][(int)$mark['criterion_id']]=$mark['points'];
$query=$pdo->prepare("SELECT entry_id,placement,total_score FROM {$prefix}_scoring_results WHERE competition_id=:id ORDER BY placement,entry_id");
$query->execute(['id'=>$competitionId]);
$resultByEntry=[];
foreach($query->fetchAll() as $result)$resultByEntry[(int)$result['entry_id']]=$result;
$rankedEntries=$entries;
usort($rankedEntries,static function(array $left,array $right)use($resultByEntry):int{
 $leftPlace=(int)($resultByEntry[(int)$left['id']]['placement']??PHP_INT_MAX);
 $rightPlace=(int)($resultByEntry[(int)$right['id']]['placement']??PHP_INT_MAX);
 return $leftPlace<=>$rightPlace?:((int)$left['bib_number']<=>(int)$right['bib_number']);
});
$canViewPrivateComments=Auth::isSuperAdmin();
$comments=[];
if($canViewPrivateComments){
 $query=$pdo->prepare("SELECT entry_id,judge_id,private_comment,updated_at FROM {$prefix}_judge_comments WHERE competition_id=:id AND TRIM(private_comment)<>'' ORDER BY judge_id,entry_id");
 $query->execute(['id'=>$competitionId]);
 foreach($query->fetchAll() as $comment)$comments[(int)$comment['judge_id']][(int)$comment['entry_id']]=(string)$comment['private_comment'];
}

if(!$judges)$judges=[['id'=>0,'judge_name'=>'','is_chief'=>0]];
$pages=$entries?array_chunk($entries,10):[array_fill(0,10,null)];
$maximum=(float)$competition['maximum_score'];
$logo=url('public/assets/img/bdc-logo-header.png');
$eventDate=$competition['event_date']?date('j M Y',strtotime((string)$competition['event_date'])):'Date pending';
if(!empty($competition['end_date'])&&$competition['end_date']!==$competition['event_date'])$eventDate.=' – '.date('j M Y',strtotime((string)$competition['end_date']));
$location=implode(', ',array_filter([trim((string)($competition['venue']??'')),trim((string)($competition['event_country']??''))]));
function dcSheetNumber(float $value):string{return rtrim(rtrim(number_format($value,2,'.',''),'0'),'.');}
?><!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e($competition['category_name'])?> · Detailed Judge Results</title>
<style>
*{box-sizing:border-box}
:root{--ink:#111827;--muted:#596579;--line:#6b7280;--soft:#eef2f6;--navy:#12203a;--wine:#671b38}
html,body{margin:0;background:#e8edf3;color:var(--ink);font-family:Arial,Helvetica,sans-serif}
.toolbar{position:sticky;top:0;z-index:5;display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px 18px;background:#111827;color:#fff;flex-wrap:wrap;min-height:58px}
.toolbar>div:last-child{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.toolbar a,.toolbar button{border:1px solid #fff;border-radius:7px;background:#fff;color:#111827;padding:9px 14px;font-weight:700;text-decoration:none;cursor:pointer}
.sheet{width:297mm;min-height:210mm;margin:8mm auto;background:#fff;padding:9mm 10mm 8mm;display:flex;flex-direction:column;page-break-after:always;break-after:page;box-shadow:0 6px 24px #1f293733}
.sheet:last-child{page-break-after:auto;break-after:auto}
.header{display:grid;grid-template-columns:27mm 1fr 46mm;align-items:center;gap:5mm;border-bottom:2px solid var(--navy);padding-bottom:3mm}
.logo{width:25mm;height:18mm;object-fit:contain}
.title{text-align:center}.title h1{font-size:18pt;line-height:1.05;margin:0;color:var(--navy)}.title h2{font-size:12pt;margin:1.5mm 0 0;text-transform:uppercase;letter-spacing:.5px}.title p{font-size:8pt;margin:1.5mm 0 0;color:var(--muted)}
.meta{text-align:right;font-size:8pt;line-height:1.45}.badge{display:inline-block;border-radius:999px;background:var(--wine);color:#fff;padding:1.2mm 2.6mm;font-size:7pt;font-weight:700;letter-spacing:.3px}
.identity{display:grid;grid-template-columns:1fr 1fr 36mm;gap:5mm;margin:4mm 0 3mm;font-size:9pt}.field{border-bottom:1px solid var(--ink);min-height:7mm;padding:1.7mm 1mm}.field b{font-size:7pt;text-transform:uppercase;color:var(--muted);display:block;margin-bottom:.7mm}.page-count{text-align:right}
.score-table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:8pt}
.score-table th,.score-table td{border:1px solid var(--line);padding:1.4mm 1mm;text-align:center;vertical-align:middle}
.score-table thead th{background:var(--soft);font-weight:700;line-height:1.12;height:17mm}
.score-table .number{width:18mm}.score-table .contestant{width:48mm;text-align:left}.score-table .total{width:19mm;font-weight:800}
.score-table tbody td{height:12.5mm}.score-table tbody .contestant{font-size:9pt;font-weight:700}
.maximum{display:block;margin-top:1mm;font-size:7pt;color:var(--muted)}
.footer{margin-top:auto;padding-top:4mm;display:grid;grid-template-columns:1fr 1fr 1fr;gap:8mm;font-size:8pt}.signature{border-top:1px solid var(--ink);padding-top:1.5mm}.signature b{display:block}.note{text-align:center;color:var(--muted);font-size:7pt;line-height:1.35}
.test{color:#b42318;font-weight:800}
.comment-list{display:grid;gap:3mm;margin-top:5mm}.comment-item{border:1px solid #cbd5e1;border-left:4px solid var(--wine);border-radius:2mm;padding:3mm 4mm}.comment-item strong{display:block;font-size:10pt}.comment-item p{margin:1.5mm 0 0;white-space:pre-wrap;font-size:9pt;line-height:1.4}.confidential{margin-top:3mm;color:var(--wine);font-size:8pt;font-weight:800;text-transform:uppercase;letter-spacing:.4px}
.summary-table .contestant{width:52mm}.summary-table .number{width:18mm}.summary-table .placement{width:18mm;font-size:11pt;font-weight:900;color:var(--wine)}.summary-table .combined{width:24mm;font-weight:900;background:#fff8e8}.summary-intro{display:flex;justify-content:space-between;gap:8mm;margin:4mm 0 3mm;font-size:8pt;color:var(--muted)}
@page{size:A4 landscape;margin:0}
@media print{html,body{background:#fff}.toolbar{display:none}.sheet{width:297mm;min-height:210mm;margin:0;box-shadow:none}.score-table thead{display:table-header-group}.score-table tr{break-inside:avoid;page-break-inside:avoid}}
@media(max-width:900px){.sheet{transform-origin:top left}.toolbar{position:relative}}
</style>
</head>
<body>
<div class="toolbar"><div><strong><?=$test?'TEST · ':''?>Dance Cup Detailed Judge Results</strong><br><small>Criterion marks, judge subtotals and official result evidence<?=$canViewPrivateComments?' · confidential comments included':''?></small></div><div><a href="category.php?id=<?=$competitionId?><?=$test?'&data_mode=test':''?>">← Back</a><button type="button" onclick="window.print()">Print / Save PDF</button></div></div>
<section class="sheet">
 <header class="header">
  <img class="logo" src="<?=e($logo)?>" alt="BDC">
  <div class="title"><h1><?=e($competition['event_name'])?></h1><h2>Final Result</h2><p><?=e($competition['category_name'])?> · All contestants and judges</p></div>
  <div class="meta"><span class="badge"><?=$test?'TEST ONLY':'OFFICIAL'?></span><br><strong><?=e($eventDate)?></strong><?php if($location):?><br><?=e($location)?><?php endif;?></div>
 </header>
 <div class="summary-intro"><span>Each judge column is that judge’s criterion subtotal for the contestant.</span><strong><?=count($entries)?> contestants · <?=count($judges)?> judges · Maximum <?=e(dcSheetNumber($maximum))?> per judge</strong></div>
 <table class="score-table summary-table">
  <colgroup><col class="number"><col class="contestant"><?php foreach($judges as $_):?><col><?php endforeach;?><col class="combined"><col class="placement"></colgroup>
  <thead><tr><th>Contestant No.</th><th class="contestant">Participant / Team</th><?php foreach($judges as $judge):?><th>J<?=(int)$judge['judge_order']?><span class="maximum"><?=e($judge['judge_name'])?><?=(int)$judge['is_chief']?' · Chief':''?></span></th><?php endforeach;?><th>Combined Score</th><th>Place</th></tr></thead>
  <tbody><?php foreach($rankedEntries as $entry):$entryId=(int)$entry['id'];?><tr><td><strong><?=e((string)$entry['bib_number'])?></strong></td><td class="contestant"><?=e($entry['display_name'])?></td><?php foreach($judges as $judge):$subtotal=0.0;$hasJudgeMark=false;foreach($criteria as $criterion){$value=$marks[(int)$judge['id']][$entryId][(int)$criterion['id']]??null;if($value!==null&&$value!==''){$subtotal+=(float)$value;$hasJudgeMark=true;}}?><td><?=$hasJudgeMark?e(dcSheetNumber($subtotal)):'—'?></td><?php endforeach;$official=$resultByEntry[$entryId]??null;?><td class="combined"><?=$official?e(dcSheetNumber((float)$official['total_score'])):'—'?></td><td class="placement"><?=$official?'#'.(int)$official['placement']:'—'?></td></tr><?php endforeach;?><?php if(!$rankedEntries):?><tr><td colspan="<?=count($judges)+4?>" class="contestant">No contestants available.</td></tr><?php endif;?></tbody>
 </table>
 <footer class="footer"><div class="signature"><b>Scoring Administrator / Witness</b></div><div></div><div class="note">Final result. Individual judge criterion pages follow. <?=$test?'<span class="test">TEST DATA</span>':''?></div></footer>
</section>
<?php foreach($judges as $judge):foreach($pages as $pageIndex=>$pageEntries):?>
<section class="sheet">
 <header class="header">
  <img class="logo" src="<?=e($logo)?>" alt="BDC">
  <div class="title"><h1><?=e($competition['event_name'])?></h1><h2>Detailed Judge Result</h2><p><?=e($competition['category_name'])?> · <?=e(ucwords(str_replace('_',' ',(string)$competition['dance_style'])))?> · <?=e(ucwords(str_replace('_',' ',(string)$competition['competition_level'])))?></p></div>
  <div class="meta"><span class="badge"><?=$test?'TEST ONLY':'OFFICIAL'?></span><br><strong><?=e($eventDate)?></strong><?php if($location):?><br><?=e($location)?><?php endif;?></div>
 </header>
 <div class="identity">
  <div class="field"><b>Judge Name</b><?=e((string)$judge['judge_name'])?><?=(int)$judge['is_chief']?' · Chief Judge':''?></div>
  <div class="field"><b>Category</b><?=e($competition['category_name'])?></div>
  <div class="field page-count"><b>Sheet</b><?=($pageIndex+1)?> of <?=count($pages)?></div>
 </div>
 <table class="score-table">
  <colgroup><col class="number"><col class="contestant"><?php foreach($criteria as $_):?><col><?php endforeach;?><col class="total"></colgroup>
  <thead><tr><th>Contestant No.</th><th class="contestant">Participant / Team</th><?php foreach($criteria as $criterion):?><th><?=e($criterion['criterion_name'])?><span class="maximum">/ <?=e(dcSheetNumber((float)$criterion['maximum_points']))?></span></th><?php endforeach;?><th>Total<span class="maximum">/ <?=e(dcSheetNumber($maximum))?></span></th></tr></thead>
  <tbody>
  <?php foreach($pageEntries as $entry):?>
   <?php if($entry):$judgeId=(int)$judge['id'];$entryId=(int)$entry['id'];$sum=0.0;$hasMark=false;?>
   <tr><td><strong><?=e((string)$entry['bib_number'])?></strong></td><td class="contestant"><?=e($entry['display_name'])?></td><?php foreach($criteria as $criterion):$value=$marks[$judgeId][$entryId][(int)$criterion['id']]??'';if($value!==''){$sum+=(float)$value;$hasMark=true;}?><td><?=$value===''?'':e(dcSheetNumber((float)$value))?></td><?php endforeach;?><td class="total"><?=$hasMark?e(dcSheetNumber($sum)):''?></td></tr>
   <?php else:?><tr><td></td><td class="contestant"></td><?php foreach($criteria as $_):?><td></td><?php endforeach;?><td class="total"></td></tr><?php endif;?>
  <?php endforeach;?>
  </tbody>
 </table>
 <footer class="footer">
  <div class="signature"><b>Judge Signature</b><?=e((string)$judge['judge_name'])?></div>
  <div class="signature"><b>Scoring Administrator / Witness</b></div>
  <div class="note">Saved criterion marks and calculated judge subtotal.<br>This report does not publish or reveal the result. <?=$test?'<span class="test">TEST DATA</span>':''?></div>
 </footer>
</section>
<?php endforeach;?>
<?php $judgeComments=$comments[(int)$judge['id']]??[];if($canViewPrivateComments&&$judgeComments):?>
<section class="sheet">
 <header class="header">
  <img class="logo" src="<?=e($logo)?>" alt="BDC">
  <div class="title"><h1><?=e($competition['event_name'])?></h1><h2>Private Judge Comments</h2><p><?=e($competition['category_name'])?></p></div>
  <div class="meta"><span class="badge">SUPER ADMIN ONLY</span><br><strong><?=e($eventDate)?></strong></div>
 </header>
 <div class="identity"><div class="field"><b>Judge Name</b><?=e((string)$judge['judge_name'])?><?=(int)$judge['is_chief']?' · Chief Judge':''?></div><div class="field"><b>Category</b><?=e($competition['category_name'])?></div><div class="field page-count"><b>Visibility</b>Confidential</div></div>
 <p class="confidential">Private judging material · visible only to Super Admin</p>
 <div class="comment-list">
 <?php foreach($entries as $entry):$privateComment=trim((string)($judgeComments[(int)$entry['id']]??''));if($privateComment==='')continue;?>
  <article class="comment-item"><strong>No. <?=e((string)$entry['bib_number'])?> · <?=e($entry['display_name'])?></strong><p><?=e($privateComment)?></p></article>
 <?php endforeach;?>
 </div>
 <footer class="footer"><div class="signature"><b>Reviewed by Super Admin</b></div><div></div><div class="note">These comments are excluded from public results, projection and ordinary admin reports.</div></footer>
</section>
<?php endif;?>
<?php endforeach;?>
</body>
</html>
