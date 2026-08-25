<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Database;
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
$q=$pdo->prepare("SELECT id,bib_number contestant_number,display_name FROM {$p}_entries WHERE competition_id=:competition AND status='active' ORDER BY bib_number,id");
$q->execute(['competition'=>$competition]);$entries=$q->fetchAll();
$q=$pdo->prepare("SELECT j.id,j.judge_name,j.judge_order,j.is_chief,COALESCE(s.status,'not_started') status,s.submitted_at FROM {$p}_judges j LEFT JOIN {$p}_judge_sessions s ON s.judge_assignment_id=j.id AND s.competition_id=j.competition_id WHERE j.competition_id=:competition ORDER BY j.is_chief DESC,j.judge_order,j.id");
$q->execute(['competition'=>$competition]);$judges=$q->fetchAll();
$q=$pdo->prepare("SELECT r.placement,r.total_score,e.id entry_id,e.bib_number contestant_number,e.display_name FROM {$p}_scoring_results r JOIN {$p}_entries e ON e.id=r.entry_id WHERE r.competition_id=:competition ORDER BY r.placement,e.bib_number");
$q->execute(['competition'=>$competition]);$results=$q->fetchAll();
if(!$results){
    $q=$pdo->prepare("SELECT e.id entry_id,e.bib_number contestant_number,e.display_name,COALESCE(SUM(m.points),0) total_score FROM {$p}_entries e LEFT JOIN {$p}_marks m ON m.entry_id=e.id AND m.competition_id=e.competition_id WHERE e.competition_id=:competition AND e.status='active' GROUP BY e.id ORDER BY total_score DESC,e.bib_number");
    $q->execute(['competition'=>$competition]);$results=$q->fetchAll();
    $place=0;$lastTotal=null;foreach($results as $i=>&$row){$total=(float)$row['total_score'];if($lastTotal===null||$total<$lastTotal){$place=$i+1;}$row['placement']=$place;$lastTotal=$total;}unset($row);
}
$active=null;foreach($entries as $entry)if((int)$entry['id']===(int)$state['active_entry_id']){$active=$entry;break;}
echo json_encode(['ok'=>true,'state'=>$state,'entries'=>$entries,'judges'=>$judges,'results'=>$results,'active_entry'=>$active],JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
