<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use App\Core\Database;
use App\Services\LiveDisplaySessionService;
use App\Services\JudgeDirectoryService;
use App\Services\ProjectionNameService;

$pdo=Database::connection();
$token=trim((string)($_GET['token']??''));
$session=LiveDisplaySessionService::byToken($pdo,$token);
if(!$session||(string)$session['screen_type']!=='final_results'||empty($session['results_unlocked'])){
    http_response_code(403);
    exit('Final results are not available.');
}

$projectorTheme=(string)($session['screen_theme']??'midnight_burgundy');
$test=$session['data_mode']==='test';
$roundId=(int)$session['current_round_id'];
$rt=$test?'bdc_test_scoring_rounds':'bdc_scoring_rounds';
$et=$test?'bdc_test_events':'bdc_events';
$jt=$test?'bdc_test_scoring_judges':'bdc_scoring_judges';
$ent=$test?'bdc_test_scoring_entries':'bdc_scoring_entries';
$pt=$test?'bdc_test_scoring_final_pairs':'bdc_scoring_final_pairs';
$frt=$test?'bdc_test_scoring_final_results':'bdc_scoring_final_results';
$mt=$test?'bdc_test_scoring_final_marks':'bdc_scoring_final_marks';

$q=$pdo->prepare("SELECT r.division,r.round_type,e.name event_name FROM {$rt} r JOIN {$et} e ON e.id=r.event_id WHERE r.id=:r AND e.id=:e");
$q->execute(['r'=>$roundId,'e'=>(int)($session['active_event_id']??$session['event_id'])]);
$round=$q->fetch();
if(!$round){http_response_code(404);exit('Round not found.');}

try{JudgeDirectoryService::ensure($pdo);JudgeDirectoryService::backfillAssignments($pdo);}catch(Throwable){}

$q=$pdo->prepare("SELECT sj.id,sj.judge_order,sj.judge_name,sj.is_chief,j.full_name,j.country,j.country_code FROM {$jt} sj LEFT JOIN bdc_judges j ON j.id=sj.judge_id WHERE sj.round_id=:r ORDER BY sj.judge_order,sj.id");
$q->execute(['r'=>$roundId]);
$judges=$q->fetchAll();
$judgeProjectionNames=[];
foreach($judges as $judgeIndex=>$judge){$judgeProjectionNames[(string)$judgeIndex]=trim((string)($judge['full_name']?:$judge['judge_name']));}
$judgeProjectionNames=ProjectionNameService::abbreviateNames($judgeProjectionNames);
foreach($judges as $judgeIndex=>&$judge){$judge['full_name']=$judgeProjectionNames[(string)$judgeIndex]??'';$judge['judge_name']=$judge['full_name'];}
unset($judge);
$judgePageSize=8;
$judgeTotal=count($judges);
$judgeTotalPages=max(1,(int)ceil($judgeTotal/$judgePageSize));
$judgePage=max(1,min((int)($session['page_number']??1),$judgeTotalPages));
$judges=array_slice($judges,($judgePage-1)*$judgePageSize,$judgePageSize);

$competitorTable=$test?'bdc_test_competitors':'bdc_competitors';
$q=$pdo->prepare("SELECT fp.id,fp.pair_number,le.bib_number leader_bib,le.display_name leader_name,fe.bib_number follower_bib,fe.display_name follower_name,lc.country leader_country,fc.country follower_country,fr.final_rank FROM {$pt} fp JOIN {$ent} le ON le.id=fp.leader_entry_id LEFT JOIN {$ent} fe ON fe.id=fp.follower_entry_id LEFT JOIN {$competitorTable} lc ON lc.id=le.competitor_id LEFT JOIN {$competitorTable} fc ON fc.id=fe.competitor_id LEFT JOIN {$frt} fr ON fr.pair_id=fp.id AND fr.round_id=fp.round_id WHERE fp.round_id=:r AND fp.pairing_status='confirmed' ORDER BY COALESCE(fr.final_rank,999999),fp.pair_number");
$q->execute(['r'=>$roundId]);
$pairs=$q->fetchAll();

$q=$pdo->prepare("SELECT pair_id,judge_id,rank_value FROM {$mt} WHERE round_id=:r");
$q->execute(['r'=>$roundId]);
$cleanFinalProjectionName = static function (mixed $value, mixed $bib = null): string {
    $name = trim((string) $value);
    $bibNumber = (int) $bib;
    if ($bibNumber > 0) {
        $name = preg_replace('/^\s*BIB\s*' . preg_quote((string) $bibNumber, '/') . '\b\s*/i', '', $name) ?? $name;
    }
    $name = preg_replace('/^\s*[A-Z]{2,3}\s+(?=\S)/u', '', $name) ?? $name;
    $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
    return trim($name);
};

$marks=[];
foreach($q->fetchAll() as $m){$marks[(int)$m['pair_id']][(int)$m['judge_id']]=$m['rank_value'];}
$eventDisplayName=trim((string)(preg_replace('/\s+_?TEST\s*$/i','',(string)$round['event_name'])??$round['event_name']));
if($eventDisplayName==='')$eventDisplayName=(string)$round['event_name'];

ob_start(static fn(string $html):string=>str_replace(
    ['</title>','<body>','<div class="stage">'],
    [
        '</title><link rel="stylesheet" href="../public/css/projector-responsive-v344.css?v=344"><link rel="stylesheet" href="../public/css/projector-themes-v352.css?v=355">',
        '<body data-projector-theme="'.e($projectorTheme).'">',
        '<div class="stage"><div class="projection-brand"><img src="'.e(url('public/assets/bdc-logo.png')).'" alt="Bachata Dance Council"></div><div class="projection-official">BDC · Official Live Display</div>',
    ],
    $html,
));
?>
<!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Final Relative Placement</title>
<style>
*{box-sizing:border-box}html,body{margin:0;width:100%;height:100%;background:#030509;color:#fff;font-family:Arial,sans-serif;overflow:hidden}
.stage{width:100vw;height:100vh;padding:10vh 5vw;background:radial-gradient(circle at top,#4a101e,#111827 52%,#030509);display:flex;flex-direction:column}
.event{text-align:center;font-size:clamp(24px,2.05vw,50px);font-weight:900;line-height:1.05}
.meta{text-align:center;color:#ffb7c3;font-size:clamp(13px,1vw,24px);margin-top:.12em}
h1{text-align:center;font-size:clamp(28px,2.35vw,58px);margin:.18em 0 .32em;line-height:1}
.wrap{flex:1;min-height:0;display:flex;align-items:stretch;justify-content:center}
table{width:100%;height:100%;border-collapse:collapse;table-layout:fixed;background:rgba(17,24,39,.88);font-size:clamp(13px,.92vw,22px)}
thead{height:9%}tbody{height:91%}tbody tr{height:calc(100% / var(--row-count))}
th,td{border:1px solid rgba(255,255,255,.28);padding:0 .28em;text-align:center}
th{background:#7d2638}th:first-child,td:first-child{width:9%;font-size:clamp(18px,1.18vw,28px);font-weight:950}
th:nth-child(2),td:nth-child(2){width:51%;text-align:left;padding-left:.55em;padding-right:.55em}
th:nth-child(n+3){font-size:clamp(13px,.8vw,19px);line-height:1.02}th:nth-child(n+3) small{display:block;font-size:clamp(10px,.58vw,14px);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:.2em}
td:nth-child(n+3){font-size:clamp(19px,1.18vw,28px);font-weight:950}
.aud-couple{display:table-cell;overflow:hidden;vertical-align:middle}
.aud-couple-row{position:relative;display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);align-items:center;gap:clamp(34px,2.5vw,62px);width:100%;min-width:0;overflow:hidden}
.aud-person{display:flex;align-items:center;gap:clamp(6px,.4vw,10px);min-width:0;overflow:hidden}.aud-person strong{flex:0 0 auto;font-size:clamp(18px,1.08vw,26px);white-space:nowrap}
.aud-person img{flex:0 0 auto;width:clamp(30px,1.75vw,44px);height:auto;aspect-ratio:3/2;object-fit:cover;border:1px solid rgba(255,255,255,.75);border-radius:3px}.aud-person span{min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:clamp(16px,.98vw,24px);font-weight:950}.aud-amp{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);z-index:1;font-size:clamp(19px,1.1vw,27px);font-weight:950;color:#f2cf72;line-height:1}
.nr{font-size:.72em;color:#b7c1d1;letter-spacing:.04em}.legend{display:block;margin-top:.28em;color:#cbd5e1;font-size:clamp(10px,.48em,15px);font-weight:700;letter-spacing:.05em}
.test{position:absolute;top:10vh;left:5vw;background:#ffc107;color:#111;padding:.35em .7em;border-radius:6px;font-weight:900}
@media(max-aspect-ratio:3/4){.event{font-size:clamp(20px,4vw,34px)}h1{font-size:clamp(20px,4.4vw,38px)}th:first-child,td:first-child{width:10%}th:nth-child(2),td:nth-child(2){width:50%}.aud-person strong{font-size:clamp(12px,2.2vw,20px)}.aud-person span{font-size:clamp(11px,2vw,18px)}.aud-person img{width:clamp(20px,3.5vw,34px)}th:nth-child(n+3){font-size:clamp(9px,1.7vw,14px)}th:nth-child(n+3) small{font-size:clamp(7px,1.35vw,11px)}td:nth-child(n+3){font-size:clamp(12px,2.2vw,20px)}}
</style></head><body><div class="stage">
<?php if($test):?><div class="test">TEST MODE</div><?php endif;?>
<div class="event"><?=e($eventDisplayName)?></div><div class="meta"><?=e(strtoupper(str_replace('_',' ',$round['division'])))?> · FINAL</div><h1>FINAL RELATIVE PLACEMENT<?php if($judgeTotalPages>1):?> · PAGE <?=$judgePage?> OF <?=$judgeTotalPages?><?php endif;?><span class="legend">NR = NOT RANKED BY THIS JUDGE</span></h1>
<div class="wrap"><table style="--row-count:<?=max(1,count($pairs))?>"><thead><tr><th>Final</th><th>Couple</th><?php foreach($judges as $j):?><th>J<?=(int)$j['judge_order']?><?=(int)$j['is_chief']?'★':''?><br><small><?=e((string)($j['full_name']?:$j['judge_name']))?></small></th><?php endforeach;?></tr></thead><tbody>
<?php foreach($pairs as $p):?><tr><td><?=isset($p['final_rank'])?'#'.(int)$p['final_rank']:'—'?></td><td class="aud-couple"><div class="aud-couple-row"><span class="aud-person"><strong>BIB <?=(int)$p['leader_bib']?></strong><?php if($lf=country_flag_url((string)($p['leader_country']??''))):?><img src="<?=e($lf)?>" alt="<?=e((string)$p['leader_country'])?> flag"><?php endif;?><span><?=e($cleanFinalProjectionName($p['leader_name'] ?? '', $p['leader_bib'] ?? null))?></span></span><span class="aud-amp">&amp;</span><span class="aud-person"><strong>BIB <?=(int)$p['follower_bib']?></strong><?php if($ff=country_flag_url((string)($p['follower_country']??''))):?><img src="<?=e($ff)?>" alt="<?=e((string)$p['follower_country'])?> flag"><?php endif;?><span><?=e($cleanFinalProjectionName($p['follower_name'] ?? '', $p['follower_bib'] ?? null))?></span></span></div></td><?php foreach($judges as $j):?><?php $rank=$marks[(int)$p['id']][(int)$j['id']]??null;?><td><?=$rank!==null?e((string)$rank):'<span class="nr">NR</span>'?></td><?php endforeach;?></tr><?php endforeach;?>
</tbody></table></div></div></body></html>
