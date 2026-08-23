<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$test=(string)file_get_contents($root.'/admin/scoring-tests/index.php');
$live=(string)file_get_contents($root.'/admin/scoring/core.php');

foreach(['advance_roster_direct_final','Heats are not required','Go Directly to Final','No Heats marks were required.'] as $marker){
    if(!str_contains($test,$marker))throw new RuntimeException('Test direct-Final prompt is missing '.$marker);
    if(!str_contains($live,$marker))throw new RuntimeException('Live direct-Final prompt is missing '.$marker);
}
foreach([$test,$live] as $surface){
    if(!str_contains($surface,"['leader']<1")||!str_contains($surface,"['follower']<1"))throw new RuntimeException('Empty-role protection is missing.');
    if(!str_contains($surface,"computeResults(\$pdo,\$source,\$userId)"))throw new RuntimeException('Direct-Final callback generation is missing.');
    if(!str_contains($surface,"createNextScoringRound(\$pdo,\$source,'final'"))throw new RuntimeException('Direct-Final round creation is missing.');
}

echo "OK: submitted Test and Live rosters can bypass unnecessary Heats and open Final directly\n";
