<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';
use App\Core\Database;use App\Services\ApiChangeProposalService;use App\Services\ProfileIntegrationAuth;
header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');
function actionResponse(int $status,array $body):never{http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST')actionResponse(405,['ok'=>false,'error'=>'POST required.']);$raw=(string)file_get_contents('php://input');if(strlen($raw)>2*1024*1024)actionResponse(413,['ok'=>false,'error'=>'Payload is too large.']);
if(!ProfileIntegrationAuth::verify($raw,'profile-actions')||!ProfileIntegrationAuth::allowedAnyScope(['competitors:submit','judges:submit']))actionResponse(401,['ok'=>false,'error'=>'Invalid, expired or unauthorized request signature.']);$input=json_decode($raw,true);if(!is_array($input))actionResponse(400,['ok'=>false,'error'=>'Invalid JSON payload.']);
try{actionResponse(202,['ok'=>true,'review'=>ApiChangeProposalService::submit(Database::connection(),$input)]);}catch(Throwable $e){error_log('BDC API action proposal failed: '.$e->getMessage());actionResponse(422,['ok'=>false,'error'=>$e->getMessage()]);}
