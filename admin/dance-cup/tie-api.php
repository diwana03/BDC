<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;use App\Core\Csrf;use App\Core\Database;use App\Services\DanceCupTieService;
Auth::requireAdmin();header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');$pdo=Database::connection();$test=(string)($_GET['data_mode']??$_POST['data_mode']??'')==='test';if($test&&!Auth::isSuperAdmin()){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'Super Admin required.']);exit;}$competition=(int)($_GET['id']??$_POST['id']??0);if($competition<1){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'Competition required.']);exit;}
try{
 if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'){
   $ties=DanceCupTieService::ties($pdo,$competition,$test);echo json_encode(['ok'=>true,'ties'=>$ties],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);exit;
 }
 if(!Csrf::verify($_POST['_csrf']??null))throw new RuntimeException('Invalid security token.');$action=(string)($_POST['action']??'');
 if($action==='create'){$out=DanceCupTieService::createTask($pdo,$competition,(string)($_POST['tie_key']??''),(int)(Auth::user()['id']??0),$test);echo json_encode(['ok'=>true]+$out,JSON_UNESCAPED_SLASHES);exit;}
 if($action==='cancel'){DanceCupTieService::cancel($pdo,$competition,(string)($_POST['tie_key']??''),$test);echo json_encode(['ok'=>true]);exit;}
 throw new RuntimeException('Unknown tie action.');
}catch(Throwable $e){http_response_code(400);echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
