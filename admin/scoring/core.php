<?php
// Phase 3 discipline-aware scoring core loader.
// The full legacy scoring engine remains in core-legacy.php; this wrapper normalises dance_style
// and protects Salsa from Bachata-only categories before delegating to the existing engine.
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;use App\Core\Csrf;use App\Core\Database;
Auth::requireAdmin();
$pdo=Database::connection();
if($_SERVER['REQUEST_METHOD']==='POST'&&(string)($_POST['action']??'')==='create_round'){
 $dance=(string)($_POST['dance_style']??'bachata');
 if(!in_array($dance,['bachata','salsa'],true))$dance='bachata';
 $_POST['dance_style']=$dance;
 $division=(string)($_POST['division']??'novice');
 if($dance==='salsa'&&!in_array($division,['novice','intermediate','advanced'],true)){
  http_response_code(422);exit('Salsa currently supports Novice, Intermediate and Advanced only.');
 }
}
require __DIR__.'/core-legacy.php';