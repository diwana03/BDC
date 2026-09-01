<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';
use App\Core\Database;use App\Services\ProfileDiagnosticQueryService;use App\Services\ProfileIntegrationAuth;
header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');
function diagnosticResponse(int $status,array $body):never{http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST')diagnosticResponse(405,['ok'=>false,'error'=>'POST required.']);$raw=(string)file_get_contents('php://input');if(strlen($raw)>65536)diagnosticResponse(413,['ok'=>false,'error'=>'Payload is too large.']);
if(!ProfileIntegrationAuth::verify($raw,'profile-diagnostics')||!ProfileIntegrationAuth::allowedAnyScope(['competitors:read','competitors:submit']))diagnosticResponse(401,['ok'=>false,'error'=>'Invalid, expired or unauthorized request signature.']);$input=json_decode($raw,true);if(!is_array($input))diagnosticResponse(400,['ok'=>false,'error'=>'Invalid JSON payload.']);
try{diagnosticResponse(200,['ok'=>true,'diagnostic'=>ProfileDiagnosticQueryService::run(Database::connection(),trim((string)($input['query']??'')),is_array($input['params']??null)?$input['params']:[])]);}catch(Throwable $e){error_log('BDC profile diagnostics failed: '.$e->getMessage());diagnosticResponse(422,['ok'=>false,'error'=>$e->getMessage()]);}
