<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Database;
use App\Services\CountryFlagService;
use App\Services\DanceCupScoringService;
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
$q=$pdo->prepare("SELECT e.id,e.bib_number contestant_number,e.display_name,c.country,c.photo_url FROM {$p}_entries e LEFT JOIN bdc_competitors c ON c.id=e.competitor_id WHERE e.competition_id=:competition AND e.status='active' ORDER BY e.bib_number,e.id");
$q->execute(['competition'=>$competition]);$entries=$q->fetchAll();
$q=$pdo->prepare("SELECT j.id,j.judge_name,j.judge_order,j.is_chief,d.country,d.country_code,d.photo_url,COALESCE(s.status,'not_started') status,s.submitted_at FROM {$p}_judges j LEFT JOIN bdc_judges d ON d.id=j.judge_id LEFT JOIN {$p}_judge_sessions s ON s.judge_assignment_id=j.id AND s.competition_id=j.competition_id WHERE j.competition_id=:competition ORDER BY j.is_chief DESC,j.judge_order,j.id");
$q->execute(['competition'=>$competition]);$judges=$q->fetchAll();
$q=$pdo->prepare("SELECT r.placement,r.total_score,1 has_score,e.id entry_id,e.bib_number contestant_number,e.display_name,c.country,c.photo_url FROM {$p}_scoring_results r JOIN {$p}_entries e ON e.id=r.entry_id LEFT JOIN bdc_competitors c ON c.id=e.competitor_id WHERE r.competition_id=:competition ORDER BY r.placement,e.bib_number");
$q->execute(['competition'=>$competition]);$results=$q->fetchAll();
if(!$results){
    $q=$pdo->prepare("SELECT e.id entry_id,e.bib_number contestant_number,e.display_name,c.country,c.photo_url,COALESCE(SUM(m.points),0) total_score,COUNT(m.id) mark_count FROM {$p}_entries e LEFT JOIN bdc_competitors c ON c.id=e.competitor_id LEFT JOIN {$p}_marks m ON m.entry_id=e.id AND m.competition_id=e.competition_id WHERE e.competition_id=:competition AND e.status='active' GROUP BY e.id,c.country,c.photo_url ORDER BY (COUNT(m.id)>0) DESC,total_score DESC,e.bib_number");
    $q->execute(['competition'=>$competition]);$results=$q->fetchAll();
    $place=0;$rankedCount=0;$lastTotal=null;foreach($results as &$row){$row['has_score']=(int)$row['mark_count']>0?1:0;if(!$row['has_score']){$row['placement']=null;continue;}$rankedCount++;$total=(float)$row['total_score'];if($lastTotal===null||$total<$lastTotal)$place=$rankedCount;$row['placement']=$place;$lastTotal=$total;}unset($row);
}
foreach($entries as &$entry)$entry['flag']=CountryFlagService::emoji($entry['country']??null);unset($entry);
foreach($judges as &$judge)$judge['flag']=CountryFlagService::emoji($judge['country_code']?:($judge['country']??null));unset($judge);
foreach($results as &$result)$result['flag']=CountryFlagService::emoji($result['country']??null);unset($result);
$active=null;foreach($entries as $entry)if((int)$entry['id']===(int)$state['active_entry_id']){$active=$entry;break;}
echo json_encode(['ok'=>true,'state'=>$state,'entries'=>$entries,'judges'=>$judges,'results'=>$results,'active_entry'=>$active],JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
