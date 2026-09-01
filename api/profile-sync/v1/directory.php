<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';
use App\Core\Database;use App\Services\ProfileIntegrationAuth;use App\Services\SdcReconciliationService;
header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');
function directoryResponse(int $status,array $body):never{http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
if($_SERVER['REQUEST_METHOD']!=='GET')directoryResponse(405,['ok'=>false,'error'=>'GET required.']);
$search=trim((string)($_GET['search']??''));$page=max(1,(int)($_GET['page']??1));$perPage=max(1,min(200,(int)($_GET['per_page']??100)));$context='directory:'.hash('sha256',$search.'|'.$page.'|'.$perPage);
if(!ProfileIntegrationAuth::verify('',$context)||!ProfileIntegrationAuth::allowedAnyScope(['competitors:read','competitors:submit']))directoryResponse(401,['ok'=>false,'error'=>'Invalid, expired or unauthorized request signature.']);
try{directoryResponse(200,['ok'=>true,'directory'=>SdcReconciliationService::directory(Database::connection(),$search,$page,$perPage)]);}catch(Throwable $e){error_log('BDC directory API failed: '.$e->getMessage());directoryResponse(422,['ok'=>false,'error'=>$e->getMessage()]);}
