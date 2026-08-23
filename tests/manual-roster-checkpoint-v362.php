<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$test=(string)file_get_contents($root.'/admin/scoring-tests/index.php');
$live=(string)file_get_contents($root.'/admin/scoring/core.php');

$required=[
 'ScoringRosterCheckpointService::assertEditable',
 "action==='save_competitors'",
 "action==='submit_competitors'",
 "action==='reopen_competitors'",
 'Submit Competitors',
 'Reopen Competitors',
 'Manual scoring locked.',
 'applyAutomaticTier($pdo,$roundId,false)',
];

foreach(['Test'=>$test,'Live'=>$live] as $surface=>$source){
 foreach($required as $needle){
  if(!str_contains($source,$needle)){
   fwrite(STDERR,$surface.' manual scoring is missing '.$needle.PHP_EOL);
   exit(1);
  }
 }
}

if(!str_contains($test,"state($pdo,$roundId,true)") || !str_contains($live,"state($pdo,$roundId,false)")){
 fwrite(STDERR,'Test/Live data-mode isolation is incomplete.'.PHP_EOL);
 exit(1);
}

echo "Manual roster checkpoint parity checks passed.\n";
