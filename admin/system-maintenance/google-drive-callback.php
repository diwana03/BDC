<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;use App\Services\BackupAutomationService;
Auth::requireSuperAdmin();
try{
 if(!empty($_GET['error']))throw new RuntimeException('Google authorization was cancelled: '.(string)$_GET['error']);
 $result=(new BackupAutomationService(dirname(__DIR__,2)))->completeGoogleOAuth((string)($_GET['code']??''),(string)($_GET['state']??''));
 $_SESSION['backup_oauth_message']='Google Drive connected as '.$result['account_email'].'. BDC folder: '.$result['folder_name'].'.';
}catch(Throwable $e){$_SESSION['backup_oauth_error']=$e->getMessage();}
header('Location: ./');exit;
