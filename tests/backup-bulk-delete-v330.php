<?php
declare(strict_types=1);
$root=dirname(__DIR__);$fail=[];
$checks=[
 'admin/scoring/backup-panel.php'=>['bulkScoringBackupDelete','backup_ids[]','DELETE SELECTED','scoring-backup-select'],
 'admin/scoring/core.php'=>['delete_selected_scoring_backups','Select at least one scoring backup'],
 'admin/scoring-tests/index.php'=>['delete_selected_scoring_backups','Select at least one test scoring backup'],
 'admin/scoring-backups/index.php'=>['delete_selected_scoring_backups','ScoringBackupService::deleteMany'],
 'app/Services/ScoringBackupService.php'=>['public static function deleteMany','Nothing was deleted','bulk_delete'],
 'admin/system-maintenance/index.php'=>['bulkPortalBackupDelete','backup_keys[]','delete_selected_backups','portal-backup-select'],
];
foreach($checks as $path=>$markers){$text=(string)file_get_contents($root.'/'.$path);foreach($markers as $marker)if(!str_contains($text,$marker))$fail[]="$path missing $marker";}
if($fail){fwrite(STDERR,implode("\n",$fail)."\n");exit(1);}echo "backup bulk delete v330: PASS\n";
