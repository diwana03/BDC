<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use App\Core\Database;use App\Services\LiveDisplaySessionService;
$pdo=Database::connection();$token=trim((string)($_GET['token']??''));$sound=($_GET['sound']??'0')==='1';$session=LiveDisplaySessionService::byToken($pdo,$token);if(!$session){http_response_code(404);exit('Live Display link is invalid or disabled.');}$roundId=(int)($session['current_round_id']??0);if($roundId>0)LiveDisplaySessionService::beginSelection($pdo,(int)$session['event_id'],$roundId,(string)$session['data_mode']==='test',0);header('Location: '.url('live-display/?token='.rawurlencode($token).($sound?'&sound=1':'')),true,303);exit;
