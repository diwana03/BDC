<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Services\McpOAuthService;

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$diagnosticId='oauth-'.gmdate('Ymd-His').'-'.substr(bin2hex(random_bytes(6)),0,12);
header('X-BDC-OAuth-Diagnostic: '.$diagnosticId);

if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){
    http_response_code(405);
    echo json_encode(['error'=>'invalid_request']);
    exit;
}

$grant=(string)($_POST['grant_type']??'');
$resource=(string)($_POST['resource']??'');
$clientId=(string)($_POST['client_id']??'');
$details=[
    'diagnostic_id'=>$diagnosticId,
    'grant_type'=>$grant?:'missing',
    'client_id_present'=>$clientId!=='',
    'resource_present'=>$resource!=='',
    'resource_matches'=>$resource!=='' ? hash_equals(McpOAuthService::resource(),$resource) : null,
    'redirect_uri_present'=>trim((string)($_POST['redirect_uri']??''))!=='',
    'code_verifier_present'=>trim((string)($_POST['code_verifier']??''))!=='',
    'authorization_code_present'=>trim((string)($_POST['code']??''))!=='',
    'refresh_token_present'=>trim((string)($_POST['refresh_token']??''))!=='',
];

try{
    if($grant==='authorization_code'){
        $out=McpOAuthService::exchangeCode(
            Database::connection(),
            (string)($_POST['code']??''),
            $clientId,
            (string)($_POST['redirect_uri']??''),
            (string)($_POST['code_verifier']??''),
            $resource
        );
    }elseif($grant==='refresh_token'){
        $out=McpOAuthService::refresh(
            Database::connection(),
            (string)($_POST['refresh_token']??''),
            $clientId,
            $resource
        );
    }else{
        throw new RuntimeException('Unsupported grant_type.');
    }

    $details['expires_in']=(int)($out['expires_in']??0);
    $details['scope']=(string)($out['scope']??'');
    Auth::audit(null,'mcp_oauth_token_ok',$details,'mcp_connector',null);
    echo json_encode($out,JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
    $details['exception']=get_class($e);
    $details['message']=$e->getMessage();
    Auth::audit(null,'mcp_oauth_token_error',$details,'mcp_connector',null);
    http_response_code(400);
    echo json_encode(['error'=>'invalid_grant','error_description'=>$e->getMessage(),'diagnostic_id'=>$diagnosticId]);
}
