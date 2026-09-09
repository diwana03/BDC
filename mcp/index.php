<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use App\Core\Auth;
use App\Core\Database;
use App\Services\BdcMcpService;
use App\Services\McpOAuthService;

header('Cache-Control: no-store');
header('Vary: Accept, MCP-Protocol-Version');

function mcpOut(array $body,int $status=200):never{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function mcpAccepted():never{
    http_response_code(202);
    header_remove('Content-Type');
    exit;
}
function mcpError(mixed $id,int $code,string $message,int $status=200):never{
    mcpOut(['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>$code,'message'=>$message]],$status);
}
function mcpAudit(?int $userId,string $action,array $details):void{
    Auth::audit($userId,$action,$details,'mcp_connector',null);
}

$versionManifest=json_decode((string)@file_get_contents(dirname(__DIR__).'/VERSION.json'),true);
$serverVersion=(string)($versionManifest['version']??'unknown');
header('X-BDC-MCP-Version: '.$serverVersion);

if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){
    header('Allow: POST');
    mcpOut(['error'=>'POST required.'],405);
}

$raw=(string)file_get_contents('php://input');
if(strlen($raw)>22*1024*1024)mcpOut(['error'=>'Payload too large.'],413);
$req=json_decode($raw,true);
if(!is_array($req))mcpError(null,-32700,'Parse error.',400);

$id=$req['id']??null;
$method=(string)($req['method']??'');
if(($req['jsonrpc']??'')!=='2.0'||$method==='')mcpError($id,-32600,'Invalid Request.',400);
$toolName=$method==='tools/call'?(string)($req['params']['name']??''):'';
$diagnosticId='mcp-'.gmdate('Ymd-His').'-'.substr(bin2hex(random_bytes(6)),0,12);
header('X-BDC-MCP-Diagnostic: '.$diagnosticId);

$header='';$headerSource='none';
foreach(['HTTP_AUTHORIZATION','REDIRECT_HTTP_AUTHORIZATION'] as $serverKey){
    $candidate=trim((string)($_SERVER[$serverKey]??''));
    if($candidate!==''){$header=$candidate;$headerSource=$serverKey;break;}
}
if($header===''&&function_exists('getallheaders')){
    $headers=getallheaders();
    if(is_array($headers)){
        foreach($headers as $k=>$v){
            if(strcasecmp((string)$k,'Authorization')===0){$header=trim((string)$v);$headerSource='getallheaders';break;}
        }
    }
}
if($header===''){
    foreach(['HTTP_AUTHORIZATION','REDIRECT_HTTP_AUTHORIZATION'] as $envKey){
        $candidate=trim((string)getenv($envKey));
        if($candidate!==''){$header=$candidate;$headerSource='getenv:'.$envKey;break;}
    }
}
$bearer=preg_match('/^Bearer\s+(.+)$/i',$header,$m)?trim($m[1]):'';
$mutatingTools=['stage_event_edit','stage_division_roster_sync','stage_competitor_additions','stage_competitor_photo_update'];
$required=$method==='tools/call'&&in_array($toolName,$mutatingTools,true)
    ?McpOAuthService::STAGE_SCOPE
    :McpOAuthService::READ_SCOPE;
$pdo=Database::connection();
$authDiag=[];
try{$authDiag=McpOAuthService::diagnoseAuthentication($pdo,$bearer,$required);}catch(Throwable $e){$authDiag=['diagnostic_error'=>get_class($e).': '.$e->getMessage()];}
$user=null;
try{$user=McpOAuthService::authenticate($pdo,$bearer,$required);}catch(Throwable $e){
    mcpAudit(null,'mcp_execution_auth_exception',['diagnostic_id'=>$diagnosticId,'server_version'=>$serverVersion,'method'=>$method,'tool'=>$toolName,'header_source'=>$headerSource,'header_present'=>$header!=='','required_scope'=>$required,'auth'=>$authDiag,'exception'=>get_class($e),'message'=>$e->getMessage()]);
    mcpError($id,-32603,'Internal authentication error. Diagnostic '.$diagnosticId.'.',500);
}
if(!$user){
    mcpAudit(isset($authDiag['user_id'])&&$authDiag['user_id']? (int)$authDiag['user_id']:null,'mcp_execution_auth_rejected',['diagnostic_id'=>$diagnosticId,'server_version'=>$serverVersion,'method'=>$method,'tool'=>$toolName,'header_source'=>$headerSource,'header_present'=>$header!=='','required_scope'=>$required,'auth'=>$authDiag]);
    $resource=absolute_url('mcp/oauth/resource.php');
    header('WWW-Authenticate: Bearer resource_metadata="'.$resource.'", scope="bdc.events.read bdc.events.stage"');
    mcpError($id,-32001,'Authorization required. Diagnostic '.$diagnosticId.'.',401);
}
$userId=(int)($user['user_id']??0);

if($method==='initialize'){
    $requested=(string)($req['params']['protocolVersion']??'');
    $supported=['2025-11-25','2025-06-18','2025-03-26','2024-11-05'];
    $protocol=in_array($requested,$supported,true)?$requested:'2025-06-18';
    header('MCP-Protocol-Version: '.$protocol);
    mcpAudit($userId?:null,'mcp_initialize_ok',['diagnostic_id'=>$diagnosticId,'server_version'=>$serverVersion,'protocol'=>$protocol,'header_source'=>$headerSource]);
    mcpOut(['jsonrpc'=>'2.0','id'=>$id,'result'=>[
        'protocolVersion'=>$protocol,
        'capabilities'=>['tools'=>['listChanged'=>false]],
        'serverInfo'=>['name'=>'BDC Portal','version'=>$serverVersion],
    ]]);
}

$requestProtocol=trim((string)($_SERVER['HTTP_MCP_PROTOCOL_VERSION']??''));
if($requestProtocol!=='')header('MCP-Protocol-Version: '.$requestProtocol);

if($method==='notifications/initialized')mcpAccepted();
if(str_starts_with($method,'notifications/'))mcpAccepted();
if($method==='ping')mcpOut(['jsonrpc'=>'2.0','id'=>$id,'result'=>(object)[]]);
if($method==='tools/list'){
    mcpAudit($userId?:null,'mcp_tools_list_ok',['diagnostic_id'=>$diagnosticId,'server_version'=>$serverVersion,'header_source'=>$headerSource]);
    mcpOut(['jsonrpc'=>'2.0','id'=>$id,'result'=>['tools'=>BdcMcpService::tools()]]);
}
if($method==='tools/call'){
    try{
        $args=$req['params']['arguments']??[];
        if(!is_array($args))throw new RuntimeException('Tool arguments must be an object.');
        $result=BdcMcpService::call($pdo,$toolName,$args);
        mcpAudit($userId?:null,'mcp_tool_call_ok',['diagnostic_id'=>$diagnosticId,'server_version'=>$serverVersion,'tool'=>$toolName,'header_source'=>$headerSource,'required_scope'=>$required]);
        mcpOut(['jsonrpc'=>'2.0','id'=>$id,'result'=>[
            'content'=>[['type'=>'text','text'=>json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]],
            'structuredContent'=>$result,
            'isError'=>false,
        ]]);
    }catch(Throwable $e){
        mcpAudit($userId?:null,'mcp_tool_call_error',['diagnostic_id'=>$diagnosticId,'server_version'=>$serverVersion,'tool'=>$toolName,'header_source'=>$headerSource,'required_scope'=>$required,'exception'=>get_class($e),'message'=>$e->getMessage()]);
        mcpOut(['jsonrpc'=>'2.0','id'=>$id,'result'=>[
            'content'=>[['type'=>'text','text'=>$e->getMessage().' (Diagnostic '.$diagnosticId.')']],
            'isError'=>true,
        ]]);
    }
}

mcpAudit($userId?:null,'mcp_method_not_found',['diagnostic_id'=>$diagnosticId,'server_version'=>$serverVersion,'method'=>$method]);
mcpError($id,-32601,'Method not found.');
