<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;
use App\Core\Database;
use App\Services\SchemaUpdater;
Auth::requireAdmin();
$pdo=Database::connection();
$roundId=(int)($_GET['round_id']??0);
$roundStmt=$pdo->prepare("SELECT r.*,e.name event_name,e.event_date FROM bdc_scoring_rounds r JOIN bdc_events e ON e.id=r.event_id WHERE r.id=:id");
$roundStmt->execute(['id'=>$roundId]);$round=$roundStmt->fetch();
if(!$round){http_response_code(404);exit('Round not found.');}
$j=$pdo->prepare("SELECT * FROM bdc_scoring_judges WHERE round_id=:r ORDER BY judge_order");$j->execute(['r'=>$roundId]);$judges=$j->fetchAll();
$e=$pdo->prepare("SELECT se.*,sr.total_score,sr.rank_number,sr.result_status,sr.alternate_rank FROM bdc_scoring_entries se LEFT JOIN bdc_scoring_results sr ON sr.round_id=se.round_id AND sr.entry_id=se.id WHERE se.round_id=:r AND se.entry_status='active' ORDER BY se.dance_role,COALESCE(sr.rank_number,999999),se.bib_number");$e->execute(['r'=>$roundId]);$entries=['leader'=>[],'follower'=>[]];foreach($e->fetchAll() as $row)$entries[$row['dance_role']][]=$row;
$m=$pdo->prepare("SELECT entry_id,judge_id,mark_type,alt_rank FROM bdc_scoring_marks WHERE round_id=:r");$m->execute(['r'=>$roundId]);$marks=[];foreach($m->fetchAll() as $row)$marks[$row['entry_id']][$row['judge_id']]=$row;
function auditMark(?array $m):string{if(!$m||$m['mark_type']==='blank')return '';if($m['mark_type']==='yes')return '1';return $m['mark_type']==='alt'?'A'.(int)$m['alt_rank']:'';}
$groups=array_chunk($judges,12);
?><!doctype html><html><head><meta charset="utf-8"><title><?=e($round['event_name'])?> Judge Audit</title><style>@page{size:A4 landscape;margin:7mm}body{font-family:Arial;margin:0;background:#eee}.toolbar{padding:10px;background:#fff;text-align:right}.page{width:283mm;min-height:196mm;margin:8mm auto;background:#fff;padding:7mm;page-break-after:always}h1{font-size:16pt;margin:0}h2{font-size:11pt}table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:8pt}th,td{border:1px solid #333;padding:1.3mm;text-align:center}th:nth-child(2),td:nth-child(2){text-align:left;width:42mm}.meta{font-size:8pt;color:#555}@media print{body{background:#fff}.toolbar{display:none}.page{margin:0;width:auto;min-height:0}}</style></head><body><div class="toolbar"><button onclick="window.print()">Print / Save as PDF</button></div>
<?php foreach($groups as $groupIndex=>$judgeGroup):foreach(['leader'=>'Leaders','follower'=>'Followers'] as $role=>$label):?><section class="page"><h1><?=e($round['event_name'])?></h1><h2><?=e(strtoupper($round['round_type']))?> · <?=$label?> · Judge Audit <?=$groupIndex+1?>/<?=count($groups)?></h2><div class="meta">Date <?=e((string)$round['event_date'])?> · Judges J<?= (int)$judgeGroup[0]['judge_order'] ?>–J<?= (int)$judgeGroup[count($judgeGroup)-1]['judge_order'] ?></div><table><thead><tr><th>Bib</th><th>Competitor</th><?php foreach($judgeGroup as $judge):?><th>J<?=(int)$judge['judge_order']?><?=(int)$judge['is_chief']?'★':''?></th><?php endforeach;?><th>Total</th><th>Rank</th></tr></thead><tbody><?php foreach($entries[$role] as $entry):?><tr><td><?=(int)$entry['bib_number']?></td><td><?=e($entry['display_name'])?></td><?php foreach($judgeGroup as $judge):?><td><?=e(auditMark($marks[$entry['id']][$judge['id']]??null))?></td><?php endforeach;?><td><?=number_format((float)($entry['total_score']??0),1)?></td><td><?=isset($entry['rank_number'])?'#'.(int)$entry['rank_number']:'—'?></td></tr><?php endforeach;?></tbody></table></section><?php endforeach;endforeach;?></body></html>