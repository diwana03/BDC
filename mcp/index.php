<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use App\Core\Database;use App\Services\BdcMcpService;use App\Services\McpOAuthService;
header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');header('MCP-Protocol-Version: 2025-06-18');
function mcpOut(array $body,int $status=200):never{http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function mcpError(mixed $id,int $code,string $message):never{mcpOut(['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>$code,'message'=>$message]]);}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')mcpOut(['error'=>'POST required.'],405);
$raw=(string)file_get_contents('php://input');if(strlen($raw)>1024*1024)mcpOut(['error'=>'Payload too large.'],413);$req=json_decode($raw,true);if(!is_array($req))mcpOut(['error'=>'Invalid JSON.'],400);
$header=trim((string)($_SERVER['HTTP_AUTHORIZATION']??''));$bearer=preg_match('/^Bearer\s+(.+)$/i',$header,$m)?trim($m[1]):'';$method=(string)($req['method']??'');$required=$method==='tools/call'&&(string)($req['params']['name']??'')==='stage_competitor_additions'?McpOAuthService::STAGE_SCOPE:McpOAuthService::READ_SCOPE;$user=McpOAuthService::authenticate(Database::connection(),$bearer,$required);
if(!$user){$resource=absolute_url('mcp/oauth/resource.php');header('WWW-Authenticate: Bearer resource_metadata="'.$resource.'", scope="bdc.events.read bdc.events.stage"');mcpOut(['jsonrpc'=>'2.0','id'=>$req['id']??null,'error'=>['code'=>-32001,'message'=>'Authorization required.']],401);}
$id=$req['id']??null;
if($method==='initialize')mcpOut(['jsonrpc'=>'2.0','id'=>$id,'result'=>['protocolVersion'=>'2025-06-18','capabilities'=>['tools'=>['listChanged'=>false]],'serverInfo'=>['name'=>'BDC Portal','version'=>'2.3.3-dev590']]]);
if($method==='notifications/initialized')mcpOut([],202);
if($method==='ping')mcpOut(['jsonrpc'=>'2.0','id'=>$id,'result'=>(object)[]]);
if($method==='tools/list')mcpOut(['jsonrpc'=>'2.0','id'=>$id,'result'=>['tools'=>BdcMcpService::tools()]]);
if($method==='tools/call'){try{$name=(string)($req['params']['name']??'');$args=$req['params']['arguments']??[];if(!is_array($args))throw new RuntimeException('Tool arguments must be an object.');$result=BdcMcpService::call(Database::connection(),$name,$args);mcpOut(['jsonrpc'=>'2.0','id'=>$id,'result'=>['content'=>[['type'=>'text','text'=>json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]],'structuredContent'=>$result,'isError'=>false]]);}catch(Throwable $e){mcpOut(['jsonrpc'=>'2.0','id'=>$id,'result'=>['content'=>[['type'=>'text','text'=>$e->getMessage()]],'isError'=>true]]);}}
mcpError($id,-32601,'Method not found.');
