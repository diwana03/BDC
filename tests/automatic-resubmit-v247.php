<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$automatic=(string)file_get_contents($root.'/admin/scoring/automatic-round.php');
$core=(string)file_get_contents($root.'/admin/scoring/core.php');
$test=(string)file_get_contents($root.'/admin/scoring-tests/index.php');

foreach(['Live Automatic'=>$automatic,'Test Automatic'=>$test] as $surface=>$source){
 if(!str_contains($source,'completed-round-reopen'))throw new RuntimeException($surface.' does not show the completed-round override form.');
 if(!str_contains($source,'name="resubmit_confirmation"'))throw new RuntimeException($surface.' does not require RESUBMIT confirmation.');
 if(!str_contains($source,'Auth::canOverrideCompletedScores()'))throw new RuntimeException($surface.' does not enforce the authorised scoring roles.');
}
if(!str_contains($automatic,'action="index.php?mode=automated'))throw new RuntimeException('Live Automatic override does not submit through the automatic workflow.');
if(!str_contains($automatic,"form:not(.completed-round-reopen)"))throw new RuntimeException('Completed Live Automatic controls are not disabled.');
if(!str_contains($automatic,"automatic_scoring_notice"))throw new RuntimeException('Live Automatic does not display the override result.');
if(!str_contains($core,"'reopen_completed_round'"))throw new RuntimeException('Automatic reopen action is not redirected back to the round.');

echo "Automatic completed-round RESUBMIT override verified.\n";
