<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';
use App\Core\Database;use App\Services\ProfileIntegrationAuth;use App\Services\SdcReconciliationService;
header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');
function reconcileResponse(int $status,array $body):never{http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST')reconcileResponse(405,['ok'=>false,'error'=>'POST required.']);$raw=(string)file_get_contents('php://input');if(strlen($raw)>1024*1024)reconcileResponse(413,['ok'=>false,'error'=>'Payload is too large.']);
if(!ProfileIntegrationAuth::verify($raw,'reconcile-sdc-test')||!ProfileIntegrationAuth::allowedAnyScope(['competitors:read','competitors:submit']))reconcileResponse(401,['ok'=>false,'error'=>'Invalid, expired or unauthorized request signature.']);$input=json_decode($raw,true);if(!is_array($input))reconcileResponse(400,['ok'=>false,'error'=>'Invalid JSON payload.']);
try{reconcileResponse(200,['ok'=>true,'reconciliation'=>SdcReconciliationService::reconcile(Database::connection(),$input)]);}catch(Throwable $e){error_log('BDC SDC reconciliation failed: '.$e->getMessage());reconcileResponse(422,['ok'=>false,'error'=>$e->getMessage()]);}
