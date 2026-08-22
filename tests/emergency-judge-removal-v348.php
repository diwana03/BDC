<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$files=[
 'emergency'=>(string)file_get_contents($root.'/app/Services/ScoringJudgeEmergencyService.php'),
 'assignment'=>(string)file_get_contents($root.'/app/Services/ScoringJudgeAssignmentService.php'),
 'backup'=>(string)file_get_contents($root.'/app/Services/ScoringBackupService.php'),
 'live'=>(string)file_get_contents($root.'/admin/scoring/judge-control.php'),
 'test'=>(string)file_get_contents($root.'/admin/scoring-tests/automatic-inline.php'),
 'order'=>(string)file_get_contents($root.'/public/js/judge-order-controls.js'),
 'projection'=>(string)file_get_contents($root.'/live-display/feed.php'),
];
$expect=[
 'emergency'=>['REMOVE JUDGE','emergency_judge_removed','fewer than 3 applicable judges','emergency_judge_removal','results_invalidated'],
 'assignment'=>['$affectsResults','results_invalidated','final_results'],
 'backup'=>["'judges'=>\$p.'judges'",'$hasJudgeSnapshot'],
 'live'=>['ScoringJudgeEmergencyService','Remove Judge Safely','canOverrideCompletedScores'],
 'test'=>['ScoringJudgeEmergencyService','Type REMOVE JUDGE'],
 'order'=>['data-remove-judge','Select a replacement Chief Judge'],
 'projection'=>['Judging Leaders & Followers','Judging Leaders Only','Judging Followers Only'],
];
$fail=[];foreach($expect as $key=>$markers)foreach($markers as $marker)if(!str_contains($files[$key],$marker))$fail[]=$key.' missing '.$marker;
if($fail){fwrite(STDERR,implode("\n",$fail)."\n");exit(1);}echo "Emergency judge removal v348: PASS\n";
