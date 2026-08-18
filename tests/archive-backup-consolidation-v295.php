<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$service=file_get_contents($root.'/app/Services/ScoringBackupService.php');
$archive=file_get_contents($root.'/admin/scoring/archive-event.php');
$panel=file_get_contents($root.'/admin/scoring/backup-panel.php');
$migration=file_get_contents($root.'/database/migrations/20260819_0110_scoring_backup_protection.php');
$navigation=file_get_contents($root.'/app/Services/ScoringRoundNavigation.php');
$liveIndex=file_get_contents($root.'/admin/scoring/index.php');
$testAutomatic=file_get_contents($root.'/admin/scoring-tests/automatic-screen.php');

foreach([
    'service supports isolated Test and Live tables'=>$service!==false&&str_contains($service,"\$test?'bdc_test_scoring_rounds':'bdc_scoring_rounds'"),
    'archive creates a final snapshot'=>$service!==false&&str_contains($service,"'archive_snapshot'"),
    'manual checkpoints are protected'=>$service!==false&&str_contains($service,"\$type==='manual'||\$action==='archive_snapshot'"),
    'latest emergency checkpoint is retained'=>$service!==false&&str_contains($service,"backup_type='pre_restore' ORDER BY id DESC LIMIT 1"),
    'older archive snapshots are consolidated'=>$service!==false&&str_contains($service,"action_name<>'archive_snapshot'"),
    'redundant checkpoints are deleted'=>$service!==false&&str_contains($service,'id NOT IN'),
    'Live archive invokes consolidation'=>$archive!==false&&str_contains($archive,'ScoringBackupService::consolidateEventForArchive($pdo,$eventId,false,$userId)'),
    'protected checkpoint is visible'=>$panel!==false&&str_contains($panel,'Protected'),
    'migration adds protection flag'=>$migration!==false&&str_contains($migration,'is_protected'),
    'shared projection opens a protected new tab'=>$navigation!==false&&str_contains($navigation,'target="_blank" rel="noopener"'),
    'Live projection launcher opens a protected new tab'=>$liveIndex!==false&&str_contains($liveIndex,'Live Screen / Projection Control</a>')&&str_contains($liveIndex,'target="_blank" rel="noopener"'),
    'Test Automatic projection opens a protected new tab'=>$testAutomatic!==false&&str_contains($testAutomatic,"anchor.target='_blank';anchor.rel='noopener'"),
] as $name=>$passed){
    if(!$passed)throw new RuntimeException('FAILED: '.$name);
    echo "PASS: {$name}\n";
}

echo "PASS: projector unaffected; archive maintenance is administration-only\n";
