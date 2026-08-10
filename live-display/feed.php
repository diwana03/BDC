<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use App\Core\Database;
use App\Services\LiveDisplaySessionService;
use App\Services\ProjectionSettingsService;
use App\Services\ProjectionLayoutService;
use App\Services\CountryFlagService;
use App\Services\JudgeDirectoryService;

$pdo=Database::connection();
$token=trim((string)($_GET['token']??''));
$session=LiveDisplaySessionService::byToken($pdo,$token);
if(!$session){http_response_code(404);exit('Live Display link is invalid or disabled.');}
$test=$session['data_mode']==='test';
$roundId=(int)($session['current_round_id']??0);
$type=(string)($session['screen_type']??'holding');
$page=max(1,(int)($session['page_number']??1));
$place=(string)($session['reveal_place']??'');
$eventTable=$test?'bdc_test_events':'bdc_events';
$eventStmt=$pdo->prepare("SELECT name FROM {$eventTable} WHERE id=:id LIMIT 1");
$eventStmt->execute(['id'=>$session['event_id']]);
$eventName=(string)($eventStmt->fetchColumn()?:'BACHATA DANCE COUNCIL');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
if($type==='holding'||$roundId<1){?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Holding Screen</title><style>html,body{margin:0;width:100%;height:100%;background:#000;color:#fff;font-family:Arial,sans-serif;overflow:hidden}.holding{width:100vw;height:100vh;display:flex;align-items:center;justify-content:center;text-align:center;padding:4%;background:radial-gradient(circle at top,#4a101e,#111827 55%,#030509);font-size:clamp(30px,5vw,96px);font-weight:900}</style></head><body><div class="holding"><?=e($eventName)?></div></body></html><?php exit;}
if(in_array($type,['results','winners'],true)&&empty($session['results_unlocked'])){http_response_code(403);exit('Results reveal is locked.');}

$roundTable=$test?'bdc_test_scoring_rounds':'bdc_scoring_rounds';
$entryTable=$test?'bdc_test_scoring_entries':'bdc_scoring_entries';
$judgeTable=$test?'bdc_test_scoring_judges':'bdc_scoring_judges';
$resultTable=$test?'bdc_test_scoring_results':'bdc_scoring_results';
$finalResultTable=$test?'bdc_test_scoring_final_results':'bdc_scoring_final_results';
$finalPairTable=$test?'bdc_test_scoring_final_pairs':'bdc_scoring_final_pairs';
$competitorTable=$test?'bdc_test_competitors':'bdc_competitors';

$s=$pdo->prepare("SELECT r.*,e.name event_name FROM {$roundTable} r JOIN {$eventTable} e ON e.id=r.event_id WHERE r.id=:id LIMIT 1");
$s->execute(['id'=>$roundId]);
$r=$s->fetch();
if(!$r||(int)$r['event_id']!==(int)$session['event_id']){http_response_code(404);exit('Selected round is not available for this Live Display.');}

$settings=ProjectionSettingsService::get($pdo,$roundId,$test);
$items=[];$title='';
if($type==='judges'){
 $title='JUDGES';
 try{JudgeDirectoryService::ensure($pdo);JudgeDirectoryService::backfillAssignments($pdo);}catch(Throwable){}
 $q=$pdo->prepare("SELECT sj.judge_name,sj.judge_order,sj.is_chief,sj.scoring_scope,j.full_name,j.country,j.country_code,j.photo_url FROM {$judgeTable} sj LEFT JOIN bdc_judges j ON j.id=sj.judge_id WHERE sj.round_id=:r ORDER BY sj.judge_order,sj.id");
 $q->execute(['r'=>$roundId]);$items=$q->fetchAll();
}elseif($type==='competitors'){
 $title=$r['round_type']==='final'?'FINALISTS / COUPLES':'COMPETITORS';
 $q=$pdo->prepare("SELECT se.display_name,se.bib_number,se.dance_role,c.country,c.photo_url FROM {$entryTable} se LEFT JOIN {$competitorTable} c ON c.id=se.competitor_id WHERE se.round_id=:r AND se.entry_status='active' ORDER BY se.dance_role,se.bib_number IS NULL,se.bib_number,se.display_name");
 $q->execute(['r'=>$roundId]);$items=$q->fetchAll();
}elseif(in_array($type,['callbacks','finalists'],true)){
 $title=$type==='callbacks'?'CALLBACKS':'FINALISTS';
 $q=$pdo->prepare("SELECT se.display_name,se.bib_number,se.dance_role,c.country,c.photo_url,sr.rank_number FROM {$resultTable} sr JOIN {$entryTable} se ON se.id=sr.entry_id LEFT JOIN {$competitorTable} c ON c.id=se.competitor_id WHERE sr.round_id=:r AND sr.result_status IN('callback','alternate') ORDER BY sr.rank_number,se.display_name");
 $q->execute(['r'=>$roundId]);$items=$q->fetchAll();
}elseif(in_array($type,['results','winners'],true)){
 $title=$type==='winners'?'WINNER PODIUM':'FINAL RANKING';
 try{
  $q=$pdo->prepare("SELECT fr.final_rank,le.display_name leader_name,fe.display_name follower_name,lc.country leader_country,lc.photo_url leader_photo,fc.country follower_country,fc.photo_url follower_photo FROM {$finalResultTable} fr JOIN {$finalPairTable} fp ON fp.id=fr.pair_id JOIN {$entryTable} le ON le.id=fp.leader_entry_id LEFT JOIN {$entryTable} fe ON fe.id=fp.follower_entry_id LEFT JOIN {$competitorTable} lc ON lc.id=le.competitor_id LEFT JOIN {$competitorTable} fc ON fc.id=fe.competitor_id WHERE fr.round_id=:r AND fr.final_rank BETWEEN 1 AND 5 ORDER BY fr.final_rank ASC");
  $q->execute(['r'=>$roundId]);$items=$q->fetchAll();
 }catch(Throwable){$items=[];}
 if($type==='winners'&&$place!==''&&$place!=='all'){$reveal=max(1,min(5,(int)$place));$items=array_values(array_filter($items,fn($x)=>(int)$x['final_rank']>=$reveal));}
}else{
 $title='SCORING IN PROGRESS';
 try{
  $sessionTable=$test?'bdc_test_scoring_judge_sessions':'bdc_scoring_judge_sessions';
  if($test){$q=$pdo->prepare("SELECT COUNT(*) total,SUM(status='submitted') submitted FROM {$sessionTable} WHERE round_id=:r");}
  else{$q=$pdo->prepare("SELECT COUNT(*) total,SUM(CASE WHEN s.status='submitted' THEN 1 ELSE 0 END) submitted FROM {$judgeTable} j LEFT JOIN {$sessionTable} s ON s.judge_id=j.id WHERE j.round_id=:r");}
  $q->execute(['r'=>$roundId]);$items=[$q->fetch()?:['total'=>0,'submitted'=>0]];
 }catch(Throwable){$items=[['total'=>0,'submitted'=>0]];}
}

$layout=ProjectionLayoutService::resolve((string)$settings['screen_format'],max(1,count($items)),(string)$settings['density'],$settings['custom_width']?:null,$settings['custom_height']?:null);
$ratio=$layout['ratio'];$cols=$layout['columns'];
if(in_array($type,['competitors','callbacks','finalists'],true)&&count($items)>$layout['capacity'])$items=array_slice($items,($page-1)*$layout['capacity'],$layout['capacity']);
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?></title><style>*{box-sizing:border-box}html,body{margin:0;width:100%;height:100%;background:#000;color:#fff;font-family:Arial,sans-serif;overflow:hidden}.viewport{width:100vw;height:100vh;display:flex;align-items:center;justify-content:center}.stage{aspect-ratio:<?=e((string)$ratio)?>;width:min(100vw,calc(100vh * <?=e((string)$ratio)?>));height:min(100vh,calc(100vw / <?=e((string)$ratio)?>));background:radial-gradient(circle at top,#4a101e,#111827 50%,#030509);padding:2.2%;text-align:center;overflow:hidden;display:flex;flex-direction:column}.event{font-size:clamp(18px,1.8vw,46px);font-weight:900}.meta{font-size:clamp(12px,.95vw,24px);color:#ffb7c3}.title{font-size:clamp(22px,2.2vw,54px);font-weight:900;margin:1% 0}.list{flex:1;display:grid;grid-template-columns:repeat(<?=$cols?>,minmax(0,1fr));grid-auto-rows:minmax(0,1fr);gap:.7%;min-height:0}.item{background:rgba(255,255,255,.09);border:1px solid rgba(255,255,255,.2);border-radius:10px;padding:3%;display:flex;flex-direction:column;align-items:center;justify-content:center;min-width:0;overflow:hidden;font-size:clamp(11px,1.2vw,28px)}.photo{width:min(8vw,11vh);height:min(8vw,11vh);border-radius:50%;object-fit:cover;margin-bottom:.4em}.name{font-weight:900;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%}.big{font-size:clamp(60px,10vw,220px);font-weight:900}.small{font-size:.72em;color:#cbd5e1}.podium{flex:1;display:flex;align-items:flex-end;justify-content:center;gap:1%;min-height:0}.podium-slot{width:18%;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%}.podium-person{min-height:26%;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;margin-bottom:2%;width:100%}.podium-photos{display:flex;justify-content:center;gap:.3vw;min-height:min(7vw,10vh)}.podium-photos img{width:min(6vw,9vh);height:min(6vw,9vh);border-radius:50%;object-fit:cover}.podium-name{font-weight:900;font-size:clamp(11px,1.15vw,27px);max-width:100%}.podium-country{font-size:clamp(9px,.75vw,18px);color:#ddd}.block{width:100%;border-radius:12px 12px 0 0;display:flex;align-items:flex-start;justify-content:center;padding-top:8%;font-size:clamp(30px,5vw,100px);font-weight:900;background:linear-gradient(#7d2638,#35101a);border:2px solid rgba(255,255,255,.18)}.p1{height:58%;background:linear-gradient(#d8aa37,#6e4d00)}.p2{height:46%;background:linear-gradient(#aeb5bf,#505866)}.p3{height:38%;background:linear-gradient(#a86c45,#56311d)}.p4{height:29%}.p5{height:23%}</style></head><body><div class="viewport"><div class="stage"><div class="event"><?=e($r['event_name'])?></div><div class="meta"><?=e(strtoupper(str_replace('_',' ',$r['division'])))?> · <?=e(strtoupper($r['round_type']))?></div><div class="title"><?=e($title)?></div><?php if(in_array($type,['winners','results'],true)):?><div class="podium"><?php foreach([4,2,1,3,5] as $rank):$x=null;foreach($items as $row){if((int)$row['final_rank']===$rank){$x=$row;break;}}?><div class="podium-slot"><?php if($x):?><div class="podium-person"><div class="podium-photos"><?php if(!empty($x['leader_photo'])):?><img src="<?=e($x['leader_photo'])?>" onerror="this.remove()"><?php endif;?><?php if(!empty($x['follower_photo'])):?><img src="<?=e($x['follower_photo'])?>" onerror="this.remove()"><?php endif;?></div><div class="podium-name"><?=e($x['leader_name'])?><?=!empty($x['follower_name'])?' & '.e($x['follower_name']):''?></div><div class="podium-country"><?php $parts=[];if(!empty($x['leader_country']))$parts[]=CountryFlagService::emoji($x['leader_country']).' '.$x['leader_country'];if(!empty($x['follower_country'])&&$x['follower_country']!==$x['leader_country'])$parts[]=CountryFlagService::emoji($x['follower_country']).' '.$x['follower_country'];echo e(implode(' · ',$parts));?></div></div><div class="block p<?=$rank?>"><?=$rank?></div><?php endif;?></div><?php endforeach;?></div><?php else:?><div class="list"><?php if($type==='scoring'):$x=$items[0];?><div class="item"><div class="big"><?=(int)($x['submitted']??0)?> / <?=(int)($x['total']??0)?></div><div class="small">JUDGES SUBMITTED</div></div><?php elseif($type==='judges'):foreach($items as $x):$country=$x['country']??'';?><div class="item"><?php if(!empty($x['photo_url'])):?><img class="photo" src="<?=e($x['photo_url'])?>" onerror="this.remove()"><?php endif;?><div class="name"><?=e($x['full_name']?:$x['judge_name'])?><?=(int)$x['is_chief']?' ★':''?></div><?php if($country):?><div class="small"><?=e(CountryFlagService::emoji($x['country_code']?:$country))?> <?=e($country)?></div><?php endif;?></div><?php endforeach;else:foreach($items as $x):?><div class="item"><?php if(!empty($x['photo_url'])):?><img class="photo" src="<?=e($x['photo_url'])?>" onerror="this.remove()"><?php endif;?><div class="name"><?=e($x['display_name'])?></div><div class="small"><?=!empty($x['bib_number'])?'BIB '.(int)$x['bib_number']:'BIB UNASSIGNED'?><?php if(!empty($x['country'])):?> · <?=e(CountryFlagService::emoji($x['country']))?> <?=e($x['country'])?><?php endif;?></div></div><?php endforeach;endif;?></div><?php endif;?></div></div></body></html>