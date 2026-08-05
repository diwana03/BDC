<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Services\ResultStorageService;

header('Content-Type: application/json');

try{
 Auth::requireSuperAdmin();

 if($_SERVER['REQUEST_METHOD']!=='POST'){
  throw new RuntimeException('POST required.');
 }

 if(!Csrf::verify($_POST['_csrf']??null)){
  throw new RuntimeException('Invalid security token.');
 }

 $roundId=(int)($_POST['round_id']??0);
 $category=(string)($_POST['category']??'');

 if($roundId<1 || !in_array($category,['heats','finals','points'],true)){
  throw new RuntimeException('Invalid HTML archive request.');
 }

 $file=$_FILES['html']??null;
 if(!$file || ($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK){
  throw new RuntimeException('The HTML archive was not received.');
 }

 if((int)($file['size']??0)>10*1024*1024){
  throw new RuntimeException('Each HTML archive must be 10 MB or smaller.');
 }

 $html=(string)file_get_contents((string)$file['tmp_name']);
 if(
  stripos($html,'<!doctype html')===false &&
  stripos($html,'<html')===false
 ){
  throw new RuntimeException('The uploaded archive is not valid HTML.');
 }

 $session=session_id()?:'no-session';
 $safeSession=preg_replace('/[^A-Za-z0-9_-]/','',$session)?:'session';
 $directory=ResultStorageService::root().'/.pending-html/'.$safeSession.'/'.$roundId;

 if(!is_dir($directory) && !mkdir($directory,0700,true) && !is_dir($directory)){
  throw new RuntimeException('Could not create the temporary HTML archive folder.');
 }

 $target=$directory.'/'.$category.'.html';
 if(is_file($target))@unlink($target);

 if(!move_uploaded_file((string)$file['tmp_name'],$target)){
  throw new RuntimeException('Could not save the temporary HTML archive.');
 }

 @chmod($target,0600);

 echo json_encode([
  'ok'=>true,
  'category'=>$category,
  'size'=>filesize($target)?:0,
 ],JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 http_response_code(422);
 echo json_encode([
  'ok'=>false,
  'error'=>$e->getMessage(),
 ]);
}
