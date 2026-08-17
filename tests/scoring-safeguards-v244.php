<?php
declare(strict_types=1);

require dirname(__DIR__).'/bootstrap.php';

use App\Services\HeatsScoringEngine;

$judges=[
 ['id'=>1,'is_chief'=>1,'scoring_scope'=>'all'],
 ['id'=>2,'is_chief'=>0,'scoring_scope'=>'all'],
 ['id'=>3,'is_chief'=>0,'scoring_scope'=>'all'],
];
$entries=[
 ['id'=>10,'dance_role'=>'leader'],['id'=>11,'dance_role'=>'leader'],
 ['id'=>20,'dance_role'=>'follower'],['id'=>21,'dance_role'=>'follower'],
];
$marks=[
 10=>[1=>10,2=>10,3=>0], 11=>[1=>0,2=>10,3=>0],
 20=>[1=>10,2=>10,3=>0], 21=>[1=>0,2=>10,3=>0],
];
$result=HeatsScoringEngine::calculate($judges,$entries,$marks,1);
foreach(['leader','follower'] as $role){
 $rows=$result[$role];
 if((float)$rows[0]['total_score']!==20.0||(float)$rows[1]['total_score']!==10.0){
  throw new RuntimeException('Chief mark was not included in the '.$role.' BDC total.');
 }
 if((int)$rows[0]['entry_id']!==($role==='leader'?10:20)||$rows[0]['result_status']!=='callback'){
  throw new RuntimeException('Chief-inclusive total did not select the expected '.$role.'.');
 }
}

echo "Scoring safeguard tests passed.\n";
