<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;use App\Services\BackupAutomationService;
Auth::requireSuperAdmin();

if($_SERVER['REQUEST_METHOD']==='POST'){
 header('Content-Type: application/json; charset=utf-8');
 try{
  $body=json_decode((string)file_get_contents('php://input'),true);
  $encoded=is_array($body)?(string)($body['payload']??''):'';
  $decoded=$encoded!==''?base64_decode(strtr($encoded,'-_','+/'),true):false;
  $oauth=$decoded!==false?json_decode($decoded,true):null;
  if(!is_array($oauth))throw new RuntimeException('Google authorization response was invalid. Start Connect Google Drive again.');
  if(!empty($oauth['error']))throw new RuntimeException('Google authorization was cancelled: '.(string)$oauth['error']);
  $result=(new BackupAutomationService(dirname(__DIR__,2)))->completeGoogleOAuth((string)($oauth['code']??''),(string)($oauth['state']??''));
  $_SESSION['backup_oauth_message']='Google Drive connected as '.$result['account_email'].'. BDC folder: '.$result['folder_name'].'.';
  echo json_encode(['ok'=>true,'redirect'=>'./']);
 }catch(Throwable $e){
  $_SESSION['backup_oauth_error']=$e->getMessage();
  http_response_code(400);echo json_encode(['ok'=>false,'redirect'=>'./']);
 }
 exit;
}

// Backward-compatible handling for an already-started query-mode request.
if(isset($_GET['code'])||isset($_GET['error'])){
 try{
  if(!empty($_GET['error']))throw new RuntimeException('Google authorization was cancelled: '.(string)$_GET['error']);
  $result=(new BackupAutomationService(dirname(__DIR__,2)))->completeGoogleOAuth((string)($_GET['code']??''),(string)($_GET['state']??''));
  $_SESSION['backup_oauth_message']='Google Drive connected as '.$result['account_email'].'. BDC folder: '.$result['folder_name'].'.';
 }catch(Throwable $e){$_SESSION['backup_oauth_error']=$e->getMessage();}
 header('Location: ./');exit;
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Connecting Google Drive</title></head>
<body><p id="status">Completing the secure Google Drive connection…</p>
<script>
(()=>{const status=document.getElementById('status');const values=new URLSearchParams(location.hash.slice(1));const data={code:values.get('code')||'',state:values.get('state')||'',error:values.get('error')||''};const payload=btoa(JSON.stringify(data)).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'');fetch(location.pathname,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({payload})}).then(async response=>{const result=await response.json();location.replace(result.redirect||'./');}).catch(()=>{status.textContent='Google Drive connection could not be completed. Return to Backup & Recovery and try again.';});})();
</script></body></html>
