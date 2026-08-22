<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$service=file_get_contents($root.'/app/Services/BackupAutomationService.php');
$page=file_get_contents($root.'/admin/system-maintenance/index.php');
$cron=file_get_contents($root.'/admin/system-maintenance/cron.php');
$schema=file_get_contents($root.'/app/Services/SchemaUpdater.php');

$checks=[
    'Schedule table schema'=>str_contains($schema,'CREATE TABLE IF NOT EXISTS bdc_backup_schedules'),
    'Legacy schedule migration'=>str_contains($service,"'Existing backup schedule'") && str_contains($service,'SELECT COUNT(*) FROM bdc_backup_schedules'),
    'Duplicate schedule protection'=>str_contains($service,'That backup schedule already exists.'),
    'Schedule CRUD'=>str_contains($service,'function saveSchedule') && str_contains($service,'function deleteSchedule') && str_contains($service,'function setScheduleEnabled'),
    'All due schedules runner'=>str_contains($service,'function runDue') && str_contains($cron,'runDue(null)'),
    'Schedule existence UI'=>str_contains($page,'Automated Backup Schedules') && str_contains($page,'configured</span>') && str_contains($page,"'last_run_at'") && str_contains($page,"'next_run_at'"),
    'Per-schedule controls'=>str_contains($page,'save_schedule') && str_contains($page,'toggle_schedule') && str_contains($page,'delete_schedule') && str_contains($page,'schedule_id'),
];

foreach($checks as $label=>$passed){if(!$passed){fwrite(STDERR,"FAIL: {$label}\n");exit(1);}}
echo "OK: multiple backup schedules, duplicate detection, status and due-job cron are present\n";
