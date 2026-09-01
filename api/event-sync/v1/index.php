<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';
use App\Core\Database;use App\Services\EventIntegrationService;use App\Services\ProfileIntegrationAuth;
header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');
function eventApiResponse(int $status,array $body):never{http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST')eventApiResponse(405,['ok'=>false,'error'=>'POST required.']);$raw=(string)file_get_contents('php://input');if(strlen($raw)>5*1024*1024)eventApiResponse(413,['ok'=>false,'error'=>'Payload is too large.']);if(!ProfileIntegrationAuth::verify($raw,'events:submit'))eventApiResponse(401,['ok'=>false,'error'=>'Invalid or expired request signature.']);$input=json_decode($raw,true);if(!is_array($input))eventApiResponse(400,['ok'=>false,'error'=>'Invalid JSON payload.']);try{eventApiResponse(202,['ok'=>true]+EventIntegrationService::submitBatch(Database::connection(),$input));}catch(Throwable $e){error_log('BDC event integration failed: '.$e->getMessage());eventApiResponse(422,['ok'=>false,'error'=>$e->getMessage()]);}
