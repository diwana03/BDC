<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$live=(string)file_get_contents($root.'/admin/scoring/core.php');
$test=(string)file_get_contents($root.'/admin/scoring-tests/index.php');
$automatic=(string)file_get_contents($root.'/admin/scoring/automatic-round.php');
$auth=(string)file_get_contents($root.'/app/Core/Auth.php');

foreach(['Live'=>$live,'Test'=>$test] as $surface=>$source){
 if(!str_contains($source,'round_type,scoring_mode'))throw new RuntimeException($surface.' child rounds do not persist scoring mode.');
 if(!str_contains($source,"'mode'=>\$source['scoring_mode']??'manual'"))throw new RuntimeException($surface.' child rounds do not inherit the source scoring mode.');
 if(!str_contains($source,'SET scoring_mode=:mode'))throw new RuntimeException($surface.' cannot repair an existing child with the wrong scoring mode.');
 if(!str_contains($source,"completed_round_reopened_for_resubmission"))throw new RuntimeException($surface.' lacks the audited completed-round override.');
 if(!str_contains($source,"resubmit_confirmation"))throw new RuntimeException($surface.' lacks RESUBMIT confirmation.');
}
if(!str_contains($test,'WHERE r.scoring_mode=:mode'))throw new RuntimeException('Test Manual and Automatic saved rounds are still mixed.');
if(str_contains($live,"WHERE r.status NOT IN ('completed','archived')"))throw new RuntimeException('Live dashboard still removes completed Heats and Semifinals.');
if(!str_contains($live,"?mode=<?=e(\$round['scoring_mode']??'manual')?>"))throw new RuntimeException('Live All Rounds link still forces Manual mode.');
if(!str_contains($automatic,"\$round['status']!=='completed'"))throw new RuntimeException('Automatic completed round still exposes next-round actions.');
if(!str_contains($auth,'canOverrideCompletedScores'))throw new RuntimeException('Central completed-score override permission is missing.');
foreach(['super_admin','master_scorer','scorer'] as $role)if(!str_contains($auth,"'{$role}'"))throw new RuntimeException('Completed-score override omits '.$role.'.');

echo "Round continuity and completed-score locks verified.\n";
