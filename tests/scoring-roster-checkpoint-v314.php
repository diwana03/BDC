<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$service=(string)file_get_contents($root.'/app/Services/ScoringRosterCheckpointService.php');
$liveAction=(string)file_get_contents($root.'/admin/scoring/automatic-setup-action.php');
$liveSetup=(string)file_get_contents($root.'/admin/scoring/automatic-common-setup.php');
$testDashboard=(string)file_get_contents($root.'/admin/scoring-tests/index.php');
$testAutomatic=(string)file_get_contents($root.'/admin/scoring-tests/automatic-inline.php');

foreach(['assertEditable','appears more than once','submitted and locked','snapshot_hash'] as $marker){
    if(!str_contains($service,$marker)){fwrite(STDERR,"Roster checkpoint service is incomplete: {$marker}\n");exit(1);}
}
foreach(['is already entered as','save_competitors','submit_competitors','unlock_all_judges'] as $marker){
    if(!str_contains($liveAction,$marker)){fwrite(STDERR,"Live roster action is incomplete: {$marker}\n");exit(1);}
}
foreach(['Competitor Checkpoint','Refresh Status','Backups','Open Judge Links','Emergency Scoring Control'] as $marker){
    if(!str_contains($liveSetup,$marker)){fwrite(STDERR,"Live Heats/Semifinal control is incomplete: {$marker}\n");exit(1);}
}
foreach(['Test Competitor Checkpoint','is already entered as','submit_competitors'] as $marker){
    if(!str_contains($testDashboard,$marker)){fwrite(STDERR,"Test roster checkpoint is incomplete: {$marker}\n");exit(1);}
}
foreach(['Refresh Status','Backups','Open Judge Links','Emergency Scoring Control','unlock_all_judges'] as $marker){
    if(!str_contains($testAutomatic,$marker)){fwrite(STDERR,"Test Heats/Semifinal control is incomplete: {$marker}\n");exit(1);}
}

if(str_contains($liveAction,'ON DUPLICATE KEY UPDATE bib_number=VALUES(bib_number)')||str_contains($testDashboard,'ON DUPLICATE KEY UPDATE bib_number=VALUES(bib_number)')){
    fwrite(STDERR,"Duplicate competitor add can still overwrite an existing bib.\n");exit(1);
}

echo "Scoring roster checkpoint checks passed.\n";
