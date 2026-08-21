<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Services\BackupAutomationService;

Auth::requireSuperAdmin();
header('Content-Type: application/json; charset=utf-8');

try{
 if($_SERVER['REQUEST_METHOD']!=='POST')throw new RuntimeException('Method not allowed.');
 $body=json_decode((string)file_get_contents('php://input'),true);
 $encoded=is_array($body)?(string)($body['payload']??''):'';
 $decoded=$encoded!==''?base64_decode(strtr($encoded,'-_','+/'),true):false;
 $oauth=$decoded!==false?json_decode($decoded,true):null;
 if(!is_array($oauth))throw new RuntimeException('Google authorization response was invalid. Start Connect Google Drive again.');
 if(!empty($oauth['error']))throw new RuntimeException('Google authorization was cancelled: '.(string)$oauth['error']);
 $result=(new BackupAutomationService(dirname(__DIR__,2)))->completeGoogleOAuth((string)($oauth['code']??''),(string)($oauth['state']??''),true);
 $_SESSION['backup_oauth_message']='Google Drive connected as '.$result['account_email'].'. BDC folder: '.$result['folder_name'].'.';
 echo json_encode(['ok'=>true,'redirect'=>'./']);
}catch(Throwable $e){
 $_SESSION['backup_oauth_error']=$e->getMessage();
 http_response_code(400);echo json_encode(['ok'=>false,'redirect'=>'./']);
}
