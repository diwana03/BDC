<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use App\Core\Database;
use App\Services\LiveDisplaySessionService;
$pdo=Database::connection();
$token=trim((string)($_GET['token']??''));
$session=LiveDisplaySessionService::byToken($pdo,$token);
if(!$session){http_response_code(404);exit('Live Display link is invalid or disabled.');}
$roundId=(int)($session['current_round_id']??0);
$type=(string)($session['screen_type']??'holding');
if($type==='holding'||$roundId<1){http_response_code(204);exit;}
if(in_array($type,['results','winners'],true)&&empty($session['results_unlocked'])){http_response_code(403);exit('Results reveal is locked.');}
$_GET['round_id']=$roundId;
$_GET['type']=$type;
$_GET['display_token']=$token;
$_GET['page']=max(1,(int)($session['page_number']??1));
if($type==='winners'&&!empty($session['reveal_place']))$_GET['place']=(string)$session['reveal_place'];
$target=$session['data_mode']==='test'?dirname(__DIR__).'/admin/live-screen/test-projection.php':dirname(__DIR__).'/admin/live-screen/projection.php';
require $target;
