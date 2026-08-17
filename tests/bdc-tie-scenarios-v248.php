<?php
declare(strict_types=1);

require dirname(__DIR__).'/bootstrap.php';

use App\Services\HeatsScoringEngine;

$judges=[
 ['id'=>1,'is_chief'=>1,'scoring_scope'=>'all'],
 ['id'=>2,'is_chief'=>0,'scoring_scope'=>'all'],
 ['id'=>3,'is_chief'=>0,'scoring_scope'=>'all'],
];

// Six clear callbacks followed by seven competitors tied on a Chief-inclusive
// total of 20. The complete seven-person group must remain pending, leaving
// exactly four of ten callbacks for the Chief Judge to select.
$entries=[];$marks=[];
for($id=1;$id<=13;$id++){
 $entries[]=['id'=>$id,'dance_role'=>'leader'];
 $marks[$id]=$id<=6?[1=>10,2=>10,3=>11-$id]:[1=>10,2=>10,3=>0];
}
$result=HeatsScoringEngine::calculate($judges,$entries,$marks,10)['leader'];
$callbacks=array_values(array_filter($result,fn(array $row):bool=>$row['result_status']==='callback'));
$pending=array_values(array_filter($result,fn(array $row):bool=>$row['result_status']==='tie_pending'));
if(count($callbacks)!==6||count($pending)!==7){
 throw new RuntimeException('Seven-person callback boundary tie was split before the Chief decision.');
}
foreach($pending as $row){
 if((float)$row['total_score']!==20.0||(float)$row['chief_score']!==10.0){
  throw new RuntimeException('Chief-inclusive tied total was calculated incorrectly.');
 }
}

// A tie wholly inside the three alternate positions still requires the Chief
// to assign a unique A-order; database/input order must never decide it.
$alternateEntries=[];$alternateMarks=[];
for($id=21;$id<=25;$id++){
 $alternateEntries[]=['id'=>$id,'dance_role'=>'leader'];
 $alternateMarks[$id]=$id<=22?[1=>10,2=>10,3=>10]:[1=>0,2=>10,3=>0];
}
$alternateResult=HeatsScoringEngine::calculate($judges,$alternateEntries,$alternateMarks,2)['leader'];
$alternatePending=array_values(array_filter($alternateResult,fn(array $row):bool=>$row['result_status']==='tie_pending'));
if(count($alternatePending)!==3){
 throw new RuntimeException('Alternate-only tie was silently ordered instead of sent to the Chief Judge.');
}

$resolver=(string)file_get_contents(dirname(__DIR__).'/app/Services/CallbackTieResolutionService.php');
if(str_contains($resolver,".'|'.\$row['chief_score']")){
 throw new RuntimeException('Tie groups still split by Chief mark after Chief was included in total.');
}

echo "BDC Chief-inclusive tie scenarios verified.\n";
