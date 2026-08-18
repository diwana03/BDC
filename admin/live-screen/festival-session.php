<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;use App\Core\Csrf;use App\Core\Database;use App\Services\LiveDisplaySessionService;
Auth::requireAdmin();$test=($_POST['data_mode']??'real')==='test';$target=$test?'test-index.php':'index.php';
if($_SERVER['REQUEST_METHOD']!=='POST'||!Csrf::verify($_POST['_csrf']??null)){http_response_code(419);exit('Invalid request.');}
try{$result=LiveDisplaySessionService::generateFestival(Database::connection(),(array)($_POST['event_ids']??[]),$test,(int)(Auth::user()['id']??0),(string)($_POST['group_name']??''));$_SESSION['festival_projection_notice']='Festival Live Projection ready. The shared projector is on Holding Screen.';header('Location: '.$target.'?festival_session='.(int)$result['session']['id'],true,303);}catch(Throwable $e){$_SESSION['festival_projection_error']=$e->getMessage();header('Location: '.$target,true,303);}exit;
