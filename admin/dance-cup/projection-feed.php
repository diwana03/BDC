<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Database;
use App\Services\CountryFlagService;
use App\Services\DanceCupScoringService;
use App\Services\ProjectionNameService;
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$pdo=Database::connection();
$test=(string)($_GET['data_mode']??'')==='test';
$p=$test?'bdc_test_dance_cup':'bdc_dance_cup';
$t=DanceCupScoringService::tables($test);
$token=preg_replace('/[^a-f0-9]/','',(string)($_GET['token']??''));
if(strlen($token)!==64){http_response_code(404);echo json_encode(['ok'=>false]);exit;}
$q=$pdo->prepare("SELECT p.*,e.name event_name,c.category_name,c.round_name,c.dance_style,c.competition_level,c.status competition_status FROM {$p}_event_projection p JOIN {$t['events']} e ON e.id=p.event_id JOIN {$t['competitions']} c ON c.id=p.active_competition_id WHERE p.access_token=:token LIMIT 1");
$q->execute(['token'=>$token]);$state=$q->fetch();
if(!$state){http_response_code(404);echo json_encode(['ok'=>false]);exit;}
$competition=(int)$state['active_competition_id'];
$fetch=function(string $sql,string $scope,array $params=[])use($pdo,$competition):array{try{$q=$pdo->prepare($sql);$q->execute(['competition'=>$competition]+$params);return $q->fetchAll();}catch(Throwable $e){error_log('BDC Dance Cup projector '.$scope.' feed fallback: '.$e->getMessage());return [];}};
$entries=$fetch("SELECT e.id,e.bib_number contestant_number,e.display_name,c.country,c.photo_url FROM {$p}_entries e LEFT JOIN bdc_competitors c ON c.id=e.competitor_id WHERE e.competition_id=:competition AND e.status='active' ORDER BY e.bib_number,e.id",'contestant');
if(!$entries)$entries=$fetch("SELECT e.id,e.bib_number contestant_number,e.display_name,NULL country,NULL photo_url FROM {$p}_entries e WHERE e.competition_id=:competition AND e.status='active' ORDER BY e.bib_number,e.id",'contestant-minimal');
$activeEntryId=(int)($state['active_entry_id']??0);
if(!$activeEntryId&&$entries)$activeEntryId=(int)$entries[0]['id'];
$criteriaRows=$fetch("SELECT id FROM {$p}_criteria WHERE competition_id=:competition",'criteria');
$criteriaRequired=count($criteriaRows);
$judges=$fetch("SELECT j.id,j.judge_name,j.judge_order,j.is_chief,d.country,d.country_code,d.photo_url,COALESCE(s.status,'not_started') status,s.submitted_at FROM {$p}_judges j LEFT JOIN bdc_judges d ON d.id=j.judge_id LEFT JOIN {$p}_judge_sessions s ON s.judge_assignment_id=j.id AND s.competition_id=j.competition_id WHERE j.competition_id=:competition ORDER BY j.is_chief DESC,j.judge_order,j.id",'judge');
if(!$judges)$judges=$fetch("SELECT j.id,j.judge_name,j.judge_order,j.is_chief,NULL country,NULL country_code,NULL photo_url,COALESCE(s.status,'not_started') status,s.submitted_at FROM {$p}_judges j LEFT JOIN {$p}_judge_sessions s ON s.judge_assignment_id=j.id AND s.competition_id=j.competition_id WHERE j.competition_id=:competition ORDER BY j.is_chief DESC,j.judge_order,j.id",'judge-minimal');
$markCounts=$fetch("SELECT m.entry_id,m.judge_id,COUNT(m.criterion_id) mark_count FROM {$p}_marks m WHERE m.competition_id=:competition GROUP BY m.entry_id,m.judge_id",'judge-contestant-progress');
$marksByEntry=[];
foreach($markCounts as $markRow)$marksByEntry[(int)$markRow['entry_id']][(int)$markRow['judge_id']]=(int)$markRow['mark_count'];
$progressEntry=$entries[0]??null;
foreach($entries as $entry){
    $completed=0;
    foreach($judges as $judge)if($criteriaRequired>0&&($marksByEntry[(int)$entry['id']][(int)$judge['id']]??0)>=$criteriaRequired)$completed++;
    $progressEntry=$entry;
    if($completed<count($judges))break;
}
$progressEntryId=(int)($progressEntry['id']??0);
foreach($judges as &$judge){
    $marks=(int)($marksByEntry[$progressEntryId][(int)$judge['id']]??0);
    $judge['current_mark_count']=$marks;
    $judge['contestant_status']=$criteriaRequired>0&&$marks>=$criteriaRequired?'complete':($marks>0?'in_progress':'pending');
    $judge['final_submitted']=$judge['status']==='submitted'?1:0;
    $judge['criteria_required']=$criteriaRequired;
}
unset($judge);
$results=$fetch("SELECT r.placement,r.total_score,1 has_score,e.id entry_id,e.bib_number contestant_number,e.display_name,c.country,c.photo_url FROM {$p}_scoring_results r JOIN {$p}_entries e ON e.id=r.entry_id LEFT JOIN bdc_competitors c ON c.id=e.competitor_id WHERE r.competition_id=:competition ORDER BY r.placement,e.bib_number",'result');
if(!$results){
    $results=$fetch("SELECT e.id entry_id,e.bib_number contestant_number,e.display_name,c.country,c.photo_url,COALESCE(SUM(m.points),0) total_score,COUNT(m.criterion_id) mark_count FROM {$p}_entries e LEFT JOIN bdc_competitors c ON c.id=e.competitor_id LEFT JOIN {$p}_marks m ON m.entry_id=e.id AND m.competition_id=e.competition_id WHERE e.competition_id=:competition AND e.status='active' GROUP BY e.id,e.bib_number,e.display_name,c.country,c.photo_url ORDER BY (COUNT(m.criterion_id)>0) DESC,total_score DESC,e.bib_number",'mark-result');
    if(!$results)$results=$fetch("SELECT e.id entry_id,e.bib_number contestant_number,e.display_name,NULL country,NULL photo_url,0 total_score,0 mark_count FROM {$p}_entries e WHERE e.competition_id=:competition AND e.status='active' ORDER BY e.bib_number,e.id",'result-minimal');
    $place=0;$rankedCount=0;$lastTotal=null;foreach($results as &$row){$row['has_score']=(int)$row['mark_count']>0?1:0;if(!$row['has_score']){$row['placement']=null;continue;}$rankedCount++;$total=(float)$row['total_score'];if($lastTotal===null||$total<$lastTotal)$place=$rankedCount;$row['placement']=$place;$lastTotal=$total;}unset($row);
}
// Scoreboard and podium identities must use the same linked roster profile as
// All Contestants. This also survives a result-query fallback on older hosts.
$entryIdentity=[];
foreach($entries as &$entry){
    $entry['flag']=CountryFlagService::emoji($entry['country']??null);
    $entryIdentity[(int)$entry['id']]=['photo_url'=>$entry['photo_url']??null,'country'=>$entry['country']??null];
}
unset($entry);
foreach($results as &$result){
    $identity=$entryIdentity[(int)($result['entry_id']??0)]??null;
    if($identity){
        if(empty($result['photo_url']))$result['photo_url']=$identity['photo_url'];
        if(empty($result['country']))$result['country']=$identity['country'];
    }
}
unset($result);
foreach($judges as &$judge)$judge['flag']=CountryFlagService::emoji($judge['country_code']?:($judge['country']??null));unset($judge);
foreach($results as &$result)$result['flag']=CountryFlagService::emoji($result['country']??null);unset($result);
// Match Jack and Jill projection naming: first name only, with a surname
// initial when the current projected group contains duplicate first names.
$entries=ProjectionNameService::abbreviateRows($entries,['display_name']);
$results=ProjectionNameService::abbreviateRows($results,['display_name']);
$judges=ProjectionNameService::abbreviateRows($judges,['judge_name']);
$active=null;foreach($entries as $entry)if((int)$entry['id']===$activeEntryId){$active=$entry;break;}
$progress=null;foreach($entries as $entry)if((int)$entry['id']===$progressEntryId){$progress=$entry;break;}
echo json_encode(['ok'=>true,'state'=>$state,'entries'=>$entries,'judges'=>$judges,'results'=>$results,'active_entry'=>$active,'progress_entry'=>$progress],JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
