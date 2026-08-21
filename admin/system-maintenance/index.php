<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Services\BackupAutomationService;
use App\Services\BackupService;
use App\Services\SchemaUpdater;
use App\Core\Database;

Auth::requireSuperAdmin();
$pdo=Database::connection();

$automation=new BackupAutomationService(dirname(__DIR__,2));
$manual=new BackupService(dirname(__DIR__,2));
$message='';$error='';
$userId=(int)(Auth::user()['id']??0);

function backupBytes(int|float $bytes):string{
 $units=['B','KB','MB','GB','TB'];$i=0;$value=(float)$bytes;
 while($value>=1024&&$i<count($units)-1){$value/=1024;$i++;}
 return number_format($value,$i?2:0).' '.$units[$i];
}

if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!Csrf::verify($_POST['_csrf']??null))$error='Invalid security token.';
 else{
  try{
   $action=(string)($_POST['action']??'');
   if($action==='save_settings'){
    $automation->saveSettings($_POST,$_FILES['service_account_json']??null,$_FILES['oauth_client_json']??null);
    $message='Automated backup settings saved.';
   }elseif($action==='run_now'){
    $result=$automation->run(true,$userId);
    $message='Backup created. Google Drive status: '.($result['google_drive_status']??'disabled').'.';
   }elseif($action==='test_drive'){
    $result=$automation->testGoogleDrive();
    $message='Google Drive connected: '.$result['folder_name'].' using '.($result['account']??$result['service_account']??'Google authorization').'.';
   }elseif($action==='disconnect_drive'){
    $automation->disconnectGoogleOAuth();$message='Google Drive OAuth connection removed. Automated uploads are disabled.';
   }elseif($action==='database'){
    $result=$manual->createDatabaseBackup($userId);$message=$result['name'];
   }elseif($action==='site'){
    $result=$manual->createSiteBackup($userId);$message=$result['name'];
   }elseif($action==='full'){
    $result=$manual->createFullBackup($userId);$message=$result['name'];
   }elseif($action==='delete_backup'){
    if(strtoupper(trim((string)($_POST['confirmation']??'')))!=='DELETE BACKUP')throw new RuntimeException('Type DELETE BACKUP to confirm permanent deletion.');
    $manual->delete((string)($_POST['backup_type']??''),(string)($_POST['backup_name']??''),$userId);$message='Backup permanently deleted.';
   }elseif($action==='delete_selected_backups'){
    if(strtoupper(trim((string)($_POST['confirmation']??'')))!=='DELETE SELECTED')throw new RuntimeException('Type DELETE SELECTED to confirm permanent deletion.');
    $keys=array_values(array_unique(array_filter(array_map('strval',(array)($_POST['backup_keys']??[])))));if(!$keys)throw new RuntimeException('Select at least one backup file to delete.');
    $selected=[];foreach($keys as $key){$parts=explode('|',$key,2);if(count($parts)!==2)throw new RuntimeException('Invalid selected backup.');$manual->resolve($parts[0],$parts[1]);$selected[]=$parts;}$deleted=0;foreach($selected as [$type,$name]){$manual->delete($type,$name,$userId);$deleted++;}$message=$deleted.' selected backup file'.($deleted===1?'':'s').' permanently deleted.';
   }elseif($action==='apply_backup'){
    if(strtoupper(trim((string)($_POST['confirmation']??'')))!=='APPLY BACKUP')throw new RuntimeException('Type APPLY BACKUP to confirm recovery.');
    $result=$manual->restoreDatabaseBackup((string)($_POST['backup_type']??''),(string)($_POST['backup_name']??''),$userId);$message='Backup applied. Safety copy retained as '.$result['safety_backup'].'.';
   }else throw new RuntimeException('Unknown backup action.');
  }catch(Throwable $e){$error=$e->getMessage();}
 }
}

$message=$message?:((string)($_SESSION['backup_oauth_message']??''));$error=$error?:((string)($_SESSION['backup_oauth_error']??''));unset($_SESSION['backup_oauth_message'],$_SESSION['backup_oauth_error']);
$settings=$automation->settings();$oauth=$automation->googleOAuthStatus();
$history=$automation->history(100);
$backups=$manual->listBackups();
$health=$manual->systemHealth();
$csrf=Csrf::token();
$cronUrl=url('admin/system-maintenance/cron.php').'?token='.urlencode((string)\App\Core\Config::get('backup.cron_token',''));
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Backup Dashboard | BDC Admin</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>.settings-card{border:0;border-radius:14px}.backup-action{height:100%;border:1px solid #dee2e6;border-radius:12px}.backup-action .btn{min-height:42px}.checksum{font:11px monospace;word-break:break-all}</style></head>
<body class="bg-light"><nav class="navbar navbar-dark bg-dark"><div class="container-fluid"><a class="navbar-brand" href="../">BDC Admin</a><span class="text-white small">Automated Backup & Google Drive</span></div></nav>
<main class="container py-4">
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4"><div><h1 class="h3 mb-1">Backup Dashboard</h1><p class="text-muted mb-0">Create manual recovery backups, manage the automated schedule and download available archives.</p></div><a class="btn btn-outline-dark" href="../">Back to Dashboard</a></div>
<?php if($message):?><div class="alert alert-success"><?=e($message)?></div><?php endif;?><?php if($error):?><div class="alert alert-danger"><strong>Backup error:</strong> <?=e($error)?></div><?php endif;?>

<div class="card settings-card shadow-sm mb-4"><div class="card-body">
 <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3"><div><h2 class="h5 mb-1">Manual Backup</h2><p class="text-muted small mb-0">Choose exactly what you want to protect. These actions do not change the automated schedule.</p></div><span class="badge text-bg-dark">Super Admin</span></div>
 <div class="row g-3">
  <div class="col-md-4"><div class="backup-action p-3 d-flex flex-column"><h3 class="h6">Full Portal</h3><p class="small text-muted flex-grow-1">Website files and database together in one complete recovery package.</p><form method="post" onsubmit="return confirm('Create a full portal backup now?')"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="full"><button class="btn btn-dark w-100">Create Full Backup</button></form></div></div>
  <div class="col-md-4"><div class="backup-action p-3 d-flex flex-column"><h3 class="h6">Database Only</h3><p class="small text-muted flex-grow-1">Competitors, events, scoring, points, users and other database records.</p><form method="post" onsubmit="return confirm('Create a database-only backup now?')"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="database"><button class="btn btn-primary w-100">Create Database Backup</button></form></div></div>
  <div class="col-md-4"><div class="backup-action p-3 d-flex flex-column"><h3 class="h6">Website Files Only</h3><p class="small text-muted flex-grow-1">Application code and website files without copying the database.</p><form method="post" onsubmit="return confirm('Create a website-files backup now?')"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="site"><button class="btn btn-outline-primary w-100">Create Website Backup</button></form></div></div>
 </div>
</div></div>

<div class="row g-4">
<div class="col-lg-8">
 <form method="post" enctype="multipart/form-data" class="card settings-card shadow-sm mb-4"><div class="card-body">
  <input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="save_settings">
  <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">Automation Settings</h2><label class="form-check form-switch"><input class="form-check-input" type="checkbox" name="enabled" value="1" <?=!empty($settings['enabled'])?'checked':''?>><span class="form-check-label">Enabled</span></label></div>
  <div class="row g-3">
   <div class="col-md-4"><label class="form-label">Backup type</label><select class="form-select" name="backup_type"><?php foreach(['full'=>'Full portal','database'=>'Database only','site'=>'Website files'] as $v=>$l):?><option value="<?=$v?>" <?=$settings['backup_type']===$v?'selected':''?>><?=$l?></option><?php endforeach;?></select></div>
   <div class="col-md-4"><label class="form-label">Frequency</label><select class="form-select" name="frequency" id="frequency"><?php foreach(['daily'=>'Daily','weekly'=>'Weekly','monthly'=>'Monthly'] as $v=>$l):?><option value="<?=$v?>" <?=$settings['frequency']===$v?'selected':''?>><?=$l?></option><?php endforeach;?></select></div>
   <div class="col-md-4"><label class="form-label">Backup time</label><input class="form-control" type="time" name="backup_time" value="<?=e(substr((string)$settings['backup_time'],0,5))?>"></div>
   <div class="col-md-4 weekly-field"><label class="form-label">Weekday</label><select class="form-select" name="weekday"><?php foreach([1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',7=>'Sunday'] as $v=>$l):?><option value="<?=$v?>" <?=(int)$settings['weekday']===$v?'selected':''?>><?=$l?></option><?php endforeach;?></select></div>
   <div class="col-md-4 monthly-field"><label class="form-label">Day of month</label><input class="form-control" type="number" min="1" max="28" name="month_day" value="<?=(int)$settings['month_day']?>"></div>
   <div class="col-md-4"><label class="form-label">Keep on this server</label><input class="form-control" type="number" min="1" max="100" name="server_keep_count" value="<?=(int)($settings['server_keep_count']??$settings['keep_count'])?>"><div class="form-text">Retain this many backups of each type locally.</div></div>
   <div class="col-md-4"><label class="form-label">Keep on Google Drive</label><input class="form-control" type="number" min="1" max="365" name="drive_keep_count" value="<?=(int)($settings['drive_keep_count']??30)?>"><div class="form-text">Drive retention is independent from server retention.</div></div>
  </div>
  <hr>
  <div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="h5 mb-0">Google Drive Backup</h2><div class="small text-muted">Upload the OAuth Web client JSON once, save settings, then connect the Google account that owns the backup storage.</div></div><label class="form-check form-switch"><input class="form-check-input" type="checkbox" name="google_drive_enabled" value="1" <?=!empty($settings['google_drive_enabled'])?'checked':''?>><span class="form-check-label">Upload enabled</span></label></div>
  <div class="alert <?=$oauth['connected']?'alert-success':'alert-light border'?> py-2"><strong><?=$oauth['connected']?'Connected':'Not connected'?></strong><?php if($oauth['connected']):?> as <?=e((string)$oauth['account'])?><?php elseif($oauth['client_configured']):?> · OAuth client saved and ready to connect<?php else:?> · Upload the OAuth client JSON below<?php endif;?></div>
  <div class="row g-3">
   <div class="col-md-6"><label class="form-label">Google Drive folder ID or URL</label><input class="form-control" name="google_drive_folder_id" value="<?=e((string)$settings['google_drive_folder_id'])?>" placeholder="https://drive.google.com/drive/folders/..."><div class="form-text">A folder URL or folder ID is accepted.</div></div>
   <div class="col-md-6"><label class="form-label">OAuth Web client JSON</label><input class="form-control" type="file" name="oauth_client_json" accept=".json,application/json"><div class="form-text">Stored privately with 0600 permissions. Never paste the client secret into chat.</div></div>
  </div>
  <div class="mt-4 d-flex gap-2 flex-wrap"><button class="btn btn-primary">Save Backup Settings</button><?php if($oauth['client_configured']&&!$oauth['connected']):?><a class="btn btn-success" href="google-drive-connect.php">Connect Google Drive</a><?php endif;?></div>
 </div></form>
 <?php if($oauth['connected']):?><form method="post" class="mb-4" onsubmit="return confirm('Disconnect Google Drive and disable automated uploads?')"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="disconnect_drive"><button class="btn btn-sm btn-outline-danger">Disconnect Google Drive</button></form><?php endif;?>

 <div class="card settings-card shadow-sm mb-4"><div class="card-body">
  <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h5 mb-0">Available Recovery Backups</h2><span class="badge text-bg-secondary"><?=count($backups)?> files</span></div>
  <p class="small text-muted">All manual and automated backup files currently stored on this server.</p>
  <?php if($backups):?><form id="bulkPortalBackupDelete" method="post" class="border border-danger-subtle rounded p-2 mb-3 d-flex gap-2 align-items-center flex-wrap" onsubmit="return confirm('Permanently delete every selected server backup file?');"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="delete_selected_backups"><input class="form-control form-control-sm" style="max-width:260px" name="confirmation" required placeholder="Type DELETE SELECTED"><button class="btn btn-outline-danger btn-sm">Delete Selected</button></form><?php endif;?>
  <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th style="width:42px"><input type="checkbox" aria-label="Select all backup files" onclick="document.querySelectorAll('.portal-backup-select').forEach(box=>box.checked=this.checked)"></th><th>Created</th><th>Type</th><th>File</th><th>Size</th><th>Checksum</th><th></th></tr></thead><tbody>
  <?php foreach($backups as $backup):?><tr>
   <td><input class="form-check-input portal-backup-select" type="checkbox" form="bulkPortalBackupDelete" name="backup_keys[]" value="<?=e((string)$backup['type'].'|'.(string)$backup['name'])?>" aria-label="Select <?=e((string)$backup['name'])?>"></td><td><?=e(date('Y-m-d H:i:s',(int)$backup['created_at']))?></td><td><span class="badge text-bg-light border"><?=e($backup['type']==='site'?'Website':ucfirst((string)$backup['type']))?></span></td>
   <td><?=e((string)$backup['name'])?></td><td><?=backupBytes((int)$backup['size'])?></td><td class="checksum"><?=e(substr((string)$backup['checksum'],0,16))?>&hellip;</td>
   <td class="text-end"><div class="d-flex justify-content-end gap-1"><a class="btn btn-sm btn-outline-dark" href="<?=e(url('admin/system-maintenance/download.php?type='.urlencode((string)$backup['type']).'&name='.urlencode((string)$backup['name'])))?>">Download</a><?php if(in_array($backup['type'],['database','full'],true)):?><details><summary class="btn btn-sm btn-warning">Apply</summary><form method="post" class="border rounded bg-white p-2 mt-1 text-start" style="min-width:250px" onsubmit="return confirm('Apply this backup to the current database? A fresh safety backup is created first.');"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="apply_backup"><input type="hidden" name="backup_type" value="<?=e((string)$backup['type'])?>"><input type="hidden" name="backup_name" value="<?=e((string)$backup['name'])?>"><input class="form-control form-control-sm mb-2" name="confirmation" required placeholder="Type APPLY BACKUP"><button class="btn btn-warning btn-sm w-100">Apply Database Recovery</button></form></details><?php endif;?><details><summary class="btn btn-sm btn-outline-danger">Delete</summary><form method="post" class="border border-danger rounded bg-white p-2 mt-1 text-start" style="min-width:250px" onsubmit="return confirm('Permanently delete this backup file?');"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="delete_backup"><input type="hidden" name="backup_type" value="<?=e((string)$backup['type'])?>"><input type="hidden" name="backup_name" value="<?=e((string)$backup['name'])?>"><input class="form-control form-control-sm mb-2" name="confirmation" required placeholder="Type DELETE BACKUP"><button class="btn btn-outline-danger btn-sm w-100">Delete Backup</button></form></details></div></td>
  </tr><?php endforeach;?><?php if(!$backups):?><tr><td colspan="7" class="text-muted">No recovery backup files are available yet.</td></tr><?php endif;?>
  </tbody></table></div>
 </div></div>

 <div class="card settings-card shadow-sm mb-4"><div class="card-body">
  <h2 class="h5">Automated Backup History</h2>
  <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Date</th><th>Type</th><th>Status</th><th>File</th><th>Size</th><th>Google Drive</th></tr></thead><tbody>
  <?php foreach($history as $run):?><tr>
   <td><?=e((string)($run['completed_at']?:$run['started_at']))?></td><td><?=e(ucfirst($run['backup_type']))?></td>
   <td><span class="badge <?=$run['status']==='success'?'text-bg-success':($run['status']==='failed'?'text-bg-danger':'text-bg-secondary')?>"><?=e($run['status'])?></span></td>
   <td><?=e((string)$run['file_name'])?><?php if($run['error_message']):?><div class="text-danger small"><?=e($run['error_message'])?></div><?php endif;?></td>
   <td><?=backupBytes((int)$run['file_size'])?></td>
   <td><?php if($run['google_drive_status']==='uploaded'):?><span class="badge text-bg-success">Uploaded</span><?php if($run['google_drive_link']):?> <a target="_blank" href="<?=e($run['google_drive_link'])?>">Open</a><?php endif;?><?php elseif($run['google_drive_status']==='failed'):?><span class="badge text-bg-danger">Failed</span><?php else:?><span class="text-muted">Disabled</span><?php endif;?></td>
  </tr><?php endforeach;?><?php if(!$history):?><tr><td colspan="6" class="text-muted">No automated backup history yet.</td></tr><?php endif;?>
  </tbody></table></div>
 </div></div>
</div>

<div class="col-lg-4">
 <div class="card settings-card shadow-sm mb-4"><div class="card-body"><h2 class="h5">Status</h2>
  <p><strong>Last run:</strong><br><?=e((string)($settings['last_run_at']?:'Never'))?></p>
  <p><strong>Next run:</strong><br><?=e((string)($settings['next_run_at']?:'Not scheduled'))?></p>
  <p><strong>Server retention:</strong><br>Keep <?=(int)($settings['server_keep_count']??$settings['keep_count'])?> backups of each type</p><p><strong>Drive retention:</strong><br>Keep <?=(int)($settings['drive_keep_count']??30)?> successful uploads</p>
  <div class="d-grid gap-2">
   <form method="post"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="run_now"><button class="btn btn-success w-100">Run Scheduled Type Now</button></form>
   <form method="post"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="test_drive"><button class="btn btn-outline-primary w-100">Test Google Drive</button></form>
  </div>
 </div></div>
 <div class="card settings-card shadow-sm mb-4"><div class="card-body"><h2 class="h5">Server Cron</h2><p class="small text-muted">Run this URL every 5–15 minutes. The portal creates a backup only when the configured schedule is due.</p><input class="form-control form-control-sm" readonly value="<?=e($cronUrl)?>"></div></div>
 <div class="card settings-card shadow-sm"><div class="card-body"><h2 class="h5">System Health</h2><ul class="list-unstyled small mb-0"><li>PHP: <?=e($health['php_version'])?></li><li>MySQL: <?=e($health['mysql_version'])?></li><li>Free disk: <?=backupBytes($health['disk_free'])?></li><li>ZIP: <?=$health['zip_available']?'Available':'Missing'?></li><li>Backup folder: <?=$health['backup_writable']?'Writable':'Not writable'?></li></ul></div></div>
</div>
</div>
</main>
<script>
function toggleSchedule(){const v=document.getElementById('frequency').value;document.querySelectorAll('.weekly-field').forEach(x=>x.style.display=v==='weekly'?'block':'none');document.querySelectorAll('.monthly-field').forEach(x=>x.style.display=v==='monthly'?'block':'none');}
document.getElementById('frequency').addEventListener('change',toggleSchedule);toggleSchedule();
</script></body></html>
