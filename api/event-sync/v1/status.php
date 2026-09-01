<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';
use App\Core\Database;use App\Services\EventIntegrationService;use App\Services\ProfileIntegrationAuth;
header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');
function eventStatusResponse(int $status,array $body):never{http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
if($_SERVER['REQUEST_METHOD']!=='GET')eventStatusResponse(405,['ok'=>false,'error'=>'GET required.']);$batch=trim((string)($_GET['batch_key']??''));if($batch==='')eventStatusResponse(400,['ok'=>false,'error'=>'batch_key is required.']);if(!ProfileIntegrationAuth::verify('','events:status:'.$batch))eventStatusResponse(401,['ok'=>false,'error'=>'Invalid or expired request signature.']);$status=EventIntegrationService::batchStatus(Database::connection(),$batch);if(!$status)eventStatusResponse(404,['ok'=>false,'error'=>'Batch not found.']);eventStatusResponse(200,['ok'=>true,'batch'=>$status]);
