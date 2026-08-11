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
    $automation->saveSettings($_POST,$_FILES['service_account_json']??null);
    $message='Automated backup settings saved.';
   }elseif($action==='run_now'){
    $result=$automation->run(true,$userId);
    $message='Backup created. Google Drive status: '.($result['google_drive_status']??'disabled').'.';
   }elseif($action==='test_drive'){
    $result=$automation->testGoogleDrive();
    $message='Google Drive connected: '.$result['folder_name'].' using '.$result['service_account'].'.';
   }elseif($action==='database'){
    $result=$manual->createDatabaseBackup($userId);$message=$result['name'];
   }elseif($action==='site'){
    $result=$manual->createSiteBackup($userId);$message=$result['name'];
   }elseif($action==='full'){
    $result=$manual->createFullBackup($userId);$message=$result['name'];
   }else throw new RuntimeException('Unknown backup action.');
  }catch(Throwable $e){$error=$e->getMessage();}
 }
}

$settings=$automation->settings();
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
   <div class="col-md-4"><label class="form-label">Backups to keep</label><input class="form-control" type="number" min="1" max="100" name="keep_count" value="<?=(int)$settings['keep_count']?>"><div class="form-text">Oldest local and Drive backups are removed after this limit.</div></div>
  </div>
  <hr>
  <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">Google Drive</h2><label class="form-check form-switch"><input class="form-check-input" type="checkbox" name="google_drive_enabled" value="1" <?=!empty($settings['google_drive_enabled'])?'checked':''?>><span class="form-check-label">Upload enabled</span></label></div>
  <div class="row g-3">
   <div class="col-md-6"><label class="form-label">Google Drive folder ID</label><input class="form-control" name="google_drive_folder_id" value="<?=e((string)$settings['google_drive_folder_id'])?>" placeholder="1AbCdEf..."><div class="form-text">Share this Drive folder with the service-account email.</div></div>
   <div class="col-md-6"><label class="form-label">Service-account JSON</label><input class="form-control" type="file" name="service_account_json" accept=".json,application/json"><div class="form-text">Stored privately with 0600 permissions. Leave blank to keep the current file.</div></div>
  </div>
  <div class="mt-4"><button class="btn btn-primary">Save Backup Settings</button></div>
 </div></form>

 <div class="card settings-card shadow-sm mb-4"><div class="card-body">
  <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h5 mb-0">Available Recovery Backups</h2><span class="badge text-bg-secondary"><?=count($backups)?> files</span></div>
  <p class="small text-muted">All manual and automated backup files currently stored on this server.</p>
  <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Created</th><th>Type</th><th>File</th><th>Size</th><th>Checksum</th><th></th></tr></thead><tbody>
  <?php foreach($backups as $backup):?><tr>
   <td><?=e(date('Y-m-d H:i:s',(int)$backup['created_at']))?></td><td><span class="badge text-bg-light border"><?=e($backup['type']==='site'?'Website':ucfirst((string)$backup['type']))?></span></td>
   <td><?=e((string)$backup['name'])?></td><td><?=backupBytes((int)$backup['size'])?></td><td class="checksum"><?=e(substr((string)$backup['checksum'],0,16))?>&hellip;</td>
   <td class="text-end"><a class="btn btn-sm btn-outline-dark" href="<?=e(url('admin/system-maintenance/download.php?type='.urlencode((string)$backup['type']).'&name='.urlencode((string)$backup['name'])))?>">Download</a></td>
  </tr><?php endforeach;?><?php if(!$backups):?><tr><td colspan="6" class="text-muted">No recovery backup files are available yet.</td></tr><?php endif;?>
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
  <p><strong>Retention:</strong><br>Keep last <?=(int)$settings['keep_count']?> successful backups</p>
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
