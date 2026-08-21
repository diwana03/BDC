<?php
declare(strict_types=1);
$root=dirname(__DIR__);$files=[
 'oauth'=>$root.'/app/Services/GoogleDriveOAuthBackupService.php',
 'automation'=>$root.'/app/Services/BackupAutomationService.php',
 'dashboard'=>$root.'/admin/system-maintenance/index.php',
 'connect'=>$root.'/admin/system-maintenance/google-drive-connect.php',
 'callback'=>$root.'/admin/system-maintenance/google-drive-callback.php',
 'popup'=>$root.'/admin/system-maintenance/google-drive-popup.php',
];
foreach($files as $name=>$path)if(!is_file($path)){fwrite(STDERR,"Missing {$name}\n");exit(1);}
$all=implode("\n",array_map(static fn(string $path):string=>(string)file_get_contents($path),$files));
foreach(['drive.file','access_type','offline','refresh_token','google-drive-oauth-token.json','ensureManagedFolder','attachAndVerifyFile','repairGoogleDriveStorage','Repair & Verify Drive Storage','Open Backup Folder','Connect Google Drive','disconnect_drive','bdc_google_oauth_state','initCodeClient','ux_mode','popup','popupRedirectUri','payload'] as $marker){if(!str_contains($all,$marker)){fwrite(STDERR,"Missing {$marker}\n");exit(1);}}
echo "google drive oauth v333: PASS\n";
