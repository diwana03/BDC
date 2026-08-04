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
