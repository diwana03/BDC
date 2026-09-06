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
if(!$round){
    http_response_code(404);
    exit('Round not found.');
}

try{
    JudgeDirectoryService::ensure($pdo);
    JudgeDirectoryService::backfillAssignments($pdo);
}catch(Throwable){}

$q=$pdo->prepare("SELECT sj.id,sj.judge_order,sj.judge_name,sj.is_chief,j.full_name,j.country,j.country_code FROM {$jt} sj LEFT JOIN bdc_judges j ON j.id=sj.judge_id WHERE sj.round_id=:r ORDER BY sj.judge_order,sj.id");
$q->execute(['r'=>$roundId]);
$judges=$q->fetchAll();
$judgeProjectionNames=[];
foreach($judges as $judgeIndex=>$judge){
    $judgeProjectionNames[(string)$judgeIndex]=trim((string)($judge['full_name']?:$judge['judge_name']));
}
$judgeProjectionNames=ProjectionNameService::abbreviateNames($judgeProjectionNames);
foreach($judges as $judgeIndex=>&$judge){
    $judge['full_name']=$judgeProjectionNames[(string)$judgeIndex]??'';
    $judge['judge_name']=$judge['full_name'];
}
unset($judge);

$competitorTable=$test?'bdc_test_competitors':'bdc_competitors';
$q=$pdo->prepare("SELECT fp.id,fp.pair_number,le.bib_number leader_bib,le.display_name leader_name,fe.bib_number follower_bib,fe.display_name follower_name,lc.country leader_country,fc.country follower_country,fr.final_rank FROM {$pt} fp JOIN {$ent} le ON le.id=fp.leader_entry_id LEFT JOIN {$ent} fe ON fe.id=fp.follower_entry_id LEFT JOIN {$competitorTable} lc ON lc.id=le.competitor_id LEFT JOIN {$competitorTable} fc ON fc.id=fe.competitor_id LEFT JOIN {$frt} fr ON fr.pair_id=fp.id AND fr.round_id=fp.round_id WHERE fp.round_id=:r AND fp.pairing_status='confirmed' ORDER BY COALESCE(fr.final_rank,999999),fp.pair_number");
$q->execute(['r'=>$roundId]);
$pairs=$q->fetchAll();

$q=$pdo->prepare("SELECT pair_id,judge_id,rank_value FROM {$mt} WHERE round_id=:r");
$q->execute(['r'=>$roundId]);
$marks=[];
foreach($q->fetchAll() as $m){
    $marks[(int)$m['pair_id']][(int)$m['judge_id']]=$m['rank_value'];
}

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
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Final Relative Placement</title>
<style>
*{box-sizing:border-box}
html,body{margin:0;width:100%;height:100%;background:#030509;color:#fff;font-family:Arial,sans-serif;overflow:hidden}
.stage{width:100vw;height:100vh;padding:1.15vw 1.55vw .85vw;background:radial-gradient(circle at top,#4a101e,#111827 52%,#030509);display:flex;flex-direction:column}
.event{text-align:center;font-size:clamp(24px,2.05vw,50px);font-weight:900}
.meta{text-align:center;color:#ffb7c3;font-size:clamp(13px,1vw,24px)}
h1{text-align:center;font-size:clamp(28px,2.35vw,58px);margin:.28em 0 .42em}
.wrap{flex:1;min-height:0;display:flex;align-items:stretch;justify-content:center}
table{width:100%;height:100%;border-collapse:collapse;table-layout:fixed;background:rgba(17,24,39,.88);font-size:clamp(13px,.92vw,22px)}
thead{height:7%}tbody{height:93%}tbody tr{height:8.333%}
th,td{border:1px solid rgba(255,255,255,.28);padding:0 .28em;text-align:center}
th{background:#7d2638}
th:first-child,td:first-child{width:9%;font-size:clamp(14px,.9vw,21px);font-weight:950}
th:nth-child(2),td:nth-child(2){width:43%;text-align:left;padding-left:.45em}
th:nth-child(n+3){font-size:clamp(10px,.62vw,15px);line-height:1.02}
th:nth-child(n+3) small{display:block;font-size:clamp(7px,.42vw,10px);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
td:nth-child(n+3){font-size:clamp(15px,.95vw,23px);font-weight:950}
.aud-couple{display:grid;grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);align-items:center;gap:clamp(7px,.5vw,12px);font-size:clamp(16px,1.08vw,26px);overflow:hidden}
.aud-person{display:grid;grid-template-columns:auto auto minmax(0,1fr);align-items:center;gap:clamp(5px,.34vw,8px);min-width:0;overflow:hidden}
.aud-person strong{font-size:.9em;white-space:nowrap}
.aud-person img{width:clamp(25px,1.5vw,36px);height:auto;aspect-ratio:3/2;object-fit:cover;border:1px solid rgba(255,255,255,.75);border-radius:3px}
.aud-person span{min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:950}
.aud-amp{font-weight:950}
.test{position:absolute;top:.7em;left:.7em;background:#ffc107;color:#111;padding:.35em .7em;border-radius:6px;font-weight:900}
</style>
</head>
<body>
<div class="stage">
<?php if($test):?><div class="test">TEST MODE</div><?php endif;?>
<div class="event"><?=e($round['event_name'])?></div>
<div class="meta"><?=e(strtoupper(str_replace('_',' ',$round['division'])))?> · FINAL</div>
<h1>FINAL RELATIVE PLACEMENT</h1>
<div class="wrap">
<table>
<thead>
<tr>
<th>Final</th>
<th>Couple</th>
<?php foreach($judges as $j):?>
<th>J<?=(int)$j['judge_order']?><?=(int)$j['is_chief']?'★':''?><br><small><?=e((string)($j['full_name']?:$j['judge_name']))?></small></th>
<?php endforeach;?>
</tr>
</thead>
<tbody>
<?php foreach($pairs as $p):?>
<tr>
<td><?=isset($p['final_rank'])?'#'.(int)$p['final_rank']:'—'?></td>
<td class="aud-couple">
<span class="aud-person"><strong>BIB <?=(int)$p['leader_bib']?></strong><?php if($lf=country_flag_url((string)($p['leader_country']??''))):?><img src="<?=e($lf)?>" alt="<?=e((string)$p['leader_country'])?> flag"><?php endif;?><span><?=e((string)$p['leader_name'])?></span></span>
<span class="aud-amp">&amp;</span>
<span class="aud-person"><strong>BIB <?=(int)$p['follower_bib']?></strong><?php if($ff=country_flag_url((string)($p['follower_country']??''))):?><img src="<?=e($ff)?>" alt="<?=e((string)$p['follower_country'])?> flag"><?php endif;?><span><?=e((string)$p['follower_name'])?></span></span>
</td>
<?php foreach($judges as $j):?><td><?=e((string)($marks[(int)$p['id']][(int)$j['id']]??''))?></td><?php endforeach;?>
</tr>
<?php endforeach;?>
</tbody>
</table>
</div>
</div>
</body>
</html>