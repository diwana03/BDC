<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Services/DivisionProgressionService.php';
use App\Services\DivisionProgressionService;

$cases=[
 ['below 20 Novice is blocked from Intermediate','intermediate',19.99,0,0,false,false,false,false],
 ['20 Novice points qualifies for Intermediate','intermediate',20,0,0,false,false,false,true],
 ['real Intermediate history prevents downgrade','intermediate',0,0,0,true,false,false,true],
 ['Advanced history blocks Intermediate','intermediate',30,0,0,true,true,false,false],
 ['Intermediate history blocks Novice','novice',0,0,0,true,false,false,false],
];
foreach($cases as [$name,$division,$novice,$intermediate,$advanced,$hi,$ha,$hs,$expected]){
 $actual=DivisionProgressionService::eligibilityFor($division,$novice,$intermediate,$advanced,'unknown',$hi,$ha,$hs)['eligible'];
 if($actual!==$expected){fwrite(STDERR,"FAIL: $name\n");exit(1);}echo "PASS: $name\n";
}
