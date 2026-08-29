<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;use App\Core\Csrf;use App\Core\Database;use App\Services\ScoringEventDuplicateService;
Auth::requireAdmin();if(!Auth::isSuperAdmin()){http_response_code(403);exit('Super Admin access required.');}if($_SERVER['REQUEST_METHOD']!=='POST'||!Csrf::verify($_POST['_csrf']??null)){http_response_code(419);exit('Invalid security token.');}$mode=in_array((string)($_POST['test_mode']??''),['manual','automated'],true)?(string)$_POST['test_mode']:'manual';
$userId=(int)(Auth::user()['id']??0)?:null;try{$copy=ScoringEventDuplicateService::duplicate(Database::connection(),(int)($_POST['event_id']??0),$userId,true);header('Location: index.php?legacy=1&test_mode='.rawurlencode($mode).'&event_duplicated=1&new_event_id='.(int)$copy['event_id'],true,303);exit;}catch(Throwable $e){header('Location: index.php?legacy=1&test_mode='.rawurlencode($mode).'&duplicate_error='.rawurlencode($e->getMessage()),true,303);exit;}
