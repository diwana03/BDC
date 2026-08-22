<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$live=file_get_contents($root.'/judge-scoring/index.php');
$test=file_get_contents($root.'/test-judge-scoring/index.php');
$liveData=file_get_contents($root.'/admin/scoring/automatic-live-data.php');
$testData=file_get_contents($root.'/admin/scoring-tests/automatic-live-data.php');

$checks=[
    'Live compact five-option heats control'=>str_contains($live,"foreach(['YES','A1','A2','A3'] as \$choice)") && str_contains($live,'grid-template-columns:repeat(5,1fr)'),
    'Test compact five-option heats control'=>str_contains($test,"['yes'=>'YES','alt1'=>'A1','alt2'=>'A2','alt3'=>'A3','no'=>'NO']") && str_contains($test,'grid-template-columns:repeat(5,1fr)'),
    'Live private heats comments'=>str_contains($live,'heats-comment-input') && str_contains($live,"'bdc-heats-comment-"),
    'Test private heats comments'=>str_contains($test,'heats-comment-input') && str_contains($test,"'bdc-test-heats-comment-"),
    'Legacy Later removed from live judge and monitor'=>!str_contains(strtoupper($live),'LATER') && !str_contains(strtoupper($liveData),'LATER'),
    'Legacy Later removed from test judge and monitor'=>!str_contains(strtoupper($test),'LATER') && !str_contains(strtoupper($testData),'LATER'),
];

foreach($checks as $label=>$passed){
    if(!$passed){fwrite(STDERR,"FAIL: {$label}\n");exit(1);}
}

echo "OK: compact Heats/Semifinal judge controls and private comments are aligned across Test and Live\n";
