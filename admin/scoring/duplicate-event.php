<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;use App\Core\Csrf;use App\Core\Database;use App\Services\ScoringEventDuplicateService;
Auth::requireAdmin();if($_SERVER['REQUEST_METHOD']!=='POST'||!Csrf::verify($_POST['_csrf']??null)){http_response_code(419);exit('Invalid security token.');}$mode=in_array((string)($_POST['mode']??''),['manual','automated'],true)?(string)$_POST['mode']:'manual';
$userId=(int)(Auth::user()['id']??0)?:null;try{$copy=ScoringEventDuplicateService::duplicate(Database::connection(),(int)($_POST['event_id']??0),$userId,false);header('Location: index.php?mode='.rawurlencode($mode).'&event_duplicated=1&new_event_id='.(int)$copy['event_id'],true,303);exit;}catch(Throwable $e){$_SESSION['scoring_create_error']='Event copy blocked: '.$e->getMessage();header('Location: index.php?mode='.rawurlencode($mode),true,303);exit;}
