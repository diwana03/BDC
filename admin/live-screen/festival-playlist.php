<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;use App\Core\Csrf;use App\Core\Database;use App\Services\LiveDisplaySessionService;
Auth::requireAdmin();$sessionId=(int)($_POST['session_id']??0);$test=($_POST['data_mode']??'real')==='test';$target=($test?'test-index.php':'index.php').'?festival_session='.$sessionId;
if($_SERVER['REQUEST_METHOD']!=='POST'||!Csrf::verify($_POST['_csrf']??null)){http_response_code(419);exit('Invalid request.');}
try{if(($_POST['action']??'')==='stop'){LiveDisplaySessionService::stopFestivalPlaylist(Database::connection(),$sessionId,$test,(int)(Auth::user()['id']??0));$_SESSION['festival_projection_notice']='Festival results loop stopped. Projector returned to Holding Screen.';}else{if((string)($_POST['confirmation']??'')!=='1')throw new RuntimeException('Confirm that the selected podiums and Final scores are ready for public display.');LiveDisplaySessionService::saveFestivalPlaylist(Database::connection(),$sessionId,$test,(array)($_POST['slides']??[]),(int)($_POST['delay']??15),(int)(Auth::user()['id']??0));$_SESSION['festival_projection_notice']='Festival results loop started.';}}catch(Throwable $e){$_SESSION['festival_projection_error']=$e->getMessage();}header('Location: '.$target,true,303);exit;
