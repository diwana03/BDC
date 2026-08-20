<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Database;
use App\Services\GoogleFormSyncService;

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function respond(int $status,array $body):never{http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}

if($_SERVER['REQUEST_METHOD']!=='POST')respond(405,['ok'=>false,'error'=>'POST required.']);
$raw=(string)file_get_contents('php://input');
$secret=trim((string)getenv('BDC_GOOGLE_FORM_SYNC_SECRET'));
if($secret===''||strlen($secret)<32)respond(503,['ok'=>false,'error'=>'Form sync is not configured.']);
$signature=strtolower(trim((string)($_SERVER['HTTP_X_BDC_SIGNATURE']??'')));
$expected=hash_hmac('sha256',$raw,$secret);
if($signature===''||!hash_equals($expected,$signature))respond(401,['ok'=>false,'error'=>'Invalid signature.']);
if(strlen($raw)>22*1024*1024)respond(413,['ok'=>false,'error'=>'Payload is too large.']);
$input=json_decode($raw,true);
if(!is_array($input))respond(400,['ok'=>false,'error'=>'Invalid JSON payload.']);
try{
    $result=GoogleFormSyncService::process(Database::connection(),$input);
    respond($result['status']==='pending_review'?202:200,['ok'=>true]+$result);
}catch(Throwable $e){
    error_log('BDC form sync failed: '.$e->getMessage());
    respond(422,['ok'=>false,'error'=>$e->getMessage()]);
}
