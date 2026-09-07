<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
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

$header=trim((string)($_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??''));
if($header===''&&function_exists('getallheaders')){
    $headers=getallheaders();
    if(is_array($headers)){
        foreach($headers as $k=>$v){
            if(strcasecmp((string)$k,'Authorization')===0){$header=trim((string)$v);break;}
        }
    }
}
$bearer=preg_match('/^Bearer\s+(.+)$/i',$header,$m)?trim($m[1]):'';
$mutatingTools=['stage_competitor_additions','stage_competitor_photo_update'];
$required=$method==='tools/call'&&in_array((string)($req['params']['name']??''),$mutatingTools,true)
    ?McpOAuthService::STAGE_SCOPE
    :McpOAuthService::READ_SCOPE;
$user=McpOAuthService::authenticate(Database::connection(),$bearer,$required);
if(!$user){
    $resource=absolute_url('mcp/oauth/resource.php');
    header('WWW-Authenticate: Bearer resource_metadata="'.$resource.'", scope="bdc.events.read bdc.events.stage"');
    mcpError($id,-32001,'Authorization required.',401);
}

if($method==='initialize'){
    $requested=(string)($req['params']['protocolVersion']??'');
    $supported=['2025-11-25','2025-06-18','2025-03-26','2024-11-05'];
    $protocol=in_array($requested,$supported,true)?$requested:'2025-06-18';
    header('MCP-Protocol-Version: '.$protocol);
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
if($method==='tools/list')mcpOut(['jsonrpc'=>'2.0','id'=>$id,'result'=>['tools'=>BdcMcpService::tools()]]);
if($method==='tools/call'){
    try{
        $name=(string)($req['params']['name']??'');
        $args=$req['params']['arguments']??[];
        if(!is_array($args))throw new RuntimeException('Tool arguments must be an object.');
        $result=BdcMcpService::call(Database::connection(),$name,$args);
        mcpOut(['jsonrpc'=>'2.0','id'=>$id,'result'=>[
            'content'=>[['type'=>'text','text'=>json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]],
            'structuredContent'=>$result,
            'isError'=>false,
        ]]);
    }catch(Throwable $e){
        mcpOut(['jsonrpc'=>'2.0','id'=>$id,'result'=>[
            'content'=>[['type'=>'text','text'=>$e->getMessage()]],
            'isError'=>true,
        ]]);
    }
}

mcpError($id,-32601,'Method not found.');
