<?php
declare(strict_types=1);

require dirname(__DIR__).'/bootstrap.php';

use App\Services\RelativePlacementCalculator;

$pairIds=[1,4,2,3,5];
$judgeIds=[101,102,103,104,105];
$chiefId=101;

$marks=[
 1=>[101=>1,102=>5,103=>5,104=>1,105=>2],
 4=>[101=>2,102=>1,103=>2,104=>4,105=>5],
 2=>[101=>3,102=>2,103=>4,104=>2,105=>1],
 3=>[101=>5,102=>3,103=>3,104=>5,105=>3],
 5=>[101=>4,102=>4,103=>1,104=>3,105=>4],
];

$result=RelativePlacementCalculator::calculate($pairIds,$judgeIds,$chiefId,$marks);
$order=array_column($result,'pair_id');
$expected=[1,2,4,3,5];

if($order!==$expected){
 fwrite(STDERR,'FAILED: expected '.json_encode($expected).' got '.json_encode($order).PHP_EOL);
 exit(1);
}

echo 'PASS: '.json_encode($order).PHP_EOL;

// Pathological full-profile tie: cumulative counts and sums are identical,
// but the head-to-head mini-contest must resolve before the Chief fallback.
$pairIds=[10,20,30];
$judgeIds=[201,202,203];
$marks=[
 10=>[201=>1,202=>2,203=>3],
 20=>[201=>2,202=>3,203=>1],
 30=>[201=>3,202=>1,203=>2],
];
$headToHead=RelativePlacementCalculator::calculate($pairIds,$judgeIds,201,$marks);
$headLog=$headToHead[0]['comparison_log']??[];
if(!in_array('head_to_head',array_column($headLog,'step'),true)){
 throw new RuntimeException('Relative Placement did not record the head-to-head tie step.');
}

try{
 RelativePlacementCalculator::calculate([1,2],[1,2,3],1,[
  1=>[1=>1,2=>1,3=>2],
  2=>[1=>1,2=>2,3=>1],
 ]);
 throw new RuntimeException('Duplicate Final placements were accepted.');
}catch(RuntimeException $e){
 if(!str_contains($e->getMessage(),'more than once'))throw $e;
}

echo "PASS: complex Relative Placement safeguards\n";

$partial=RelativePlacementCalculator::calculate(
 [1,2,3,4,5],
 [1,2,3],
 1,
 [
  1=>[1=>1,2=>1,3=>2],
  2=>[1=>2,2=>3,3=>1],
  3=>[1=>3,2=>2,3=>3],
  4=>[],
  5=>[],
 ],
 3
);
if(count($partial)!==3||array_column($partial,'final_rank')!==[1,2,3]){
 throw new RuntimeException('Top-N Relative Placement did not return exactly the selected ranking depth.');
}
echo "PASS: Top-N Final ranking leaves remaining couples unranked\n";
