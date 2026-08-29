<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Database;
use App\Services\DanceCupScoringService;
$pdo=Database::connection();
$token=preg_replace('/[^a-f0-9]/','',(string)($_GET['token']??''));
$test=(string)($_GET['data_mode']??'')==='test';
if(strlen($token)!==64){http_response_code(404);exit('Projector link not found.');}
DanceCupScoringService::ensureWorkspaceTables($pdo,$test);
$prefix=$test?'bdc_test_dance_cup':'bdc_dance_cup';
$query=$pdo->prepare("SELECT event_id FROM {$prefix}_event_projection WHERE access_token=:token LIMIT 1");
$query->execute(['token'=>$token]);
$eventId=(int)$query->fetchColumn();
if($eventId<1){http_response_code(404);exit('Projector link not found.');}
$pdo->prepare("UPDATE {$prefix}_event_projection SET screen_type='holding',active_entry_id=NULL,auto_cycle=0,page_number=1,state_version=state_version+1 WHERE event_id=:event")->execute(['event'=>$eventId]);
header('Cache-Control: no-store');
header('Location: '.url('admin/dance-cup/projector.php?token='.rawurlencode($token).($test?'&data_mode=test':'').'&presentation=502'),true,303);
exit;
