<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';
use App\Core\Database;use App\Services\ProfileIntegrationAuth;use App\Services\ProfileIntegrationService;
header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');
function profileApiResponse(int $status,array $body):never{http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST')profileApiResponse(405,['ok'=>false,'error'=>'POST required.']);
$raw=(string)file_get_contents('php://input');if(strlen($raw)>80*1024*1024)profileApiResponse(413,['ok'=>false,'error'=>'Payload is too large.']);if(!ProfileIntegrationAuth::verify($raw))profileApiResponse(401,['ok'=>false,'error'=>'Invalid or expired request signature.']);
$input=json_decode($raw,true);if(!is_array($input))profileApiResponse(400,['ok'=>false,'error'=>'Invalid JSON payload.']);
try{profileApiResponse(202,['ok'=>true]+ProfileIntegrationService::submitBatch(Database::connection(),$input));}catch(Throwable $e){error_log('BDC profile integration failed: '.$e->getMessage());profileApiResponse(422,['ok'=>false,'error'=>$e->getMessage()]);}
