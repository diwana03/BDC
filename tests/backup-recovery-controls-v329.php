<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$files=[
 'service'=>file_get_contents($root.'/app/Services/ScoringBackupService.php'),
 'live'=>file_get_contents($root.'/admin/scoring/core.php'),
 'test'=>file_get_contents($root.'/admin/scoring-tests/index.php'),
 'panel'=>file_get_contents($root.'/admin/scoring/backup-panel.php'),
 'central'=>file_get_contents($root.'/admin/scoring-backups/index.php'),
 'portal'=>file_get_contents($root.'/admin/system-maintenance/index.php'),
 'automation'=>file_get_contents($root.'/app/Services/BackupAutomationService.php'),
 'backup'=>file_get_contents($root.'/app/Services/BackupService.php'),
];
$fail=[];
$expect=[
 'service'=>['public static function delete','scoring_backup_deleted','round_id=:round AND data_mode=:mode'],
 'live'=>['delete_scoring_backup','DELETE BACKUP','Backups &amp; Recovery'],
 'test'=>['delete_scoring_backup','DELETE BACKUP','data_mode=test'],
 'panel'=>['Delete Backup','delete_reason','Open Backups &amp; Recovery'],
 'central'=>['delete_scoring_backup','ScoringBackupService::delete'],
 'portal'=>['apply_backup','delete_backup','server_keep_count','drive_keep_count','Google Drive folder ID or URL'],
 'automation'=>['server_keep_count','drive_keep_count','GoogleDriveBackupService::normaliseFolderId'],
 'backup'=>['restoreDatabaseBackup','createDatabaseBackup($userId)','database_restore'],
];
foreach($expect as $key=>$markers)foreach($markers as $marker)if(!str_contains((string)$files[$key],$marker))$fail[]="$key missing $marker";
if($fail){fwrite(STDERR,implode("\n",$fail)."\n");exit(1);}echo "backup recovery controls v329: PASS\n";
