<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;use App\Core\Csrf;use App\Core\Database;use App\Services\DanceCupCategoryDuplicateService;
Auth::requireAdmin();$test=(string)($_POST['data_mode']??'')==='test';if($test&&!Auth::isSuperAdmin()){http_response_code(403);exit('Super Admin access required.');}
if($_SERVER['REQUEST_METHOD']!=='POST'||!Csrf::verify($_POST['_csrf']??null)){http_response_code(419);exit('Invalid security token.');}
$userId=(int)(Auth::user()['id']??0)?:null;try{$newId=DanceCupCategoryDuplicateService::duplicate(Database::connection(),(int)($_POST['category_id']??0),$userId,$test);header('Location: category-edit.php?id='.$newId.($test?'&data_mode=test':'').'&duplicated=1',true,303);exit;}catch(Throwable $e){http_response_code(422);echo '<!doctype html><html><body style="font-family:Arial;padding:30px"><h2>Category copy blocked</h2><p>'.htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8').'</p><p><a href="workflow.php?workflow=manual">Back to Dance Cup</a></p></body></html>';}
