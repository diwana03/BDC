<?php
declare(strict_types=1);

require dirname(__DIR__).'/bootstrap.php';

use App\Services\HeatsScoringEngine;
use App\Services\RoleAdvancementService;

$assert=static function(bool $condition,string $message):void{
    if(!$condition)throw new RuntimeException($message);
};

foreach([5,10,15] as $quota){
    $direct=RoleAdvancementService::rolePlan($quota,$quota);
    $assert($direct['direct_to_final']&&$direct['yes_required']===0&&$direct['alternate_count']===0,'Quota-sized role must advance directly.');
    foreach([1,2,3,4] as $extra){
        $plan=RoleAdvancementService::rolePlan($quota+$extra,$quota);
        $assert(!$plan['direct_to_final'],'Role above quota must be judged.');
        $assert($plan['yes_required']===$quota,'Judged role must retain the tier YES quota.');
        $assert($plan['alternate_count']===min(3,$extra),'Alternate requirement must grow only to A3.');
    }
}

foreach([7,6] as $followerCount){
    $plan=RoleAdvancementService::roundPlan(8,$followerCount,5);
    $assert($plan['leader']['requires_judging']===true,'Eight leaders must run Tier 1 Heats.');
    $assert($plan['follower']['requires_judging']===true,$followerCount.' followers must still run Tier 1 Heats.');
}
$plan=RoleAdvancementService::roundPlan(8,5,5);
$assert($plan['leader']['requires_judging']===true,'Eight leaders must run Tier 1 Heats.');
$assert($plan['follower']['direct_to_final']===true,'Five followers must advance directly.');

foreach([[20,10,10],[35,15,15]] as [$leaders,$followers,$quota]){
    $mixedTierPlan=RoleAdvancementService::roundPlan($leaders,$followers,$quota);
    $assert($mixedTierPlan['leader']['requires_judging']===true,$leaders.' leaders must run Heats.');
    $assert($mixedTierPlan['follower']['direct_to_final']===true,$followers.' followers must advance directly at quota '.$quota.'.');
}

$entries=[];
for($i=1;$i<=5;$i++)$entries[]=['id'=>$i,'dance_role'=>'follower','bib_number'=>$i];
$judges=[
    ['id'=>1,'is_chief'=>1,'scoring_scope'=>'all'],
    ['id'=>2,'is_chief'=>0,'scoring_scope'=>'all'],
    ['id'=>3,'is_chief'=>0,'scoring_scope'=>'all'],
];
$calculated=HeatsScoringEngine::calculate($judges, $entries, [], 5);
$assert(count($calculated['follower'])===5,'Direct role must remain present in the established engine result.');
foreach($calculated['follower'] as $row)$assert($row['result_status']==='callback','Every direct-role competitor must be transferred by the existing callback path.');

$mixedEntries=$entries;
for($i=1;$i<=8;$i++)$mixedEntries[]=['id'=>100+$i,'dance_role'=>'leader','bib_number'=>$i];
$leaderJudges=[
    ['id'=>1,'is_chief'=>1,'scoring_scope'=>'leader'],
    ['id'=>2,'is_chief'=>0,'scoring_scope'=>'leader'],
    ['id'=>3,'is_chief'=>0,'scoring_scope'=>'leader'],
];
$mixedMarks=[];foreach($mixedEntries as $entry)if($entry['dance_role']==='leader')$mixedMarks[$entry['id']]=[1=>10,2=>10,3=>10];
$mixed=HeatsScoringEngine::calculate($leaderJudges,$mixedEntries,$mixedMarks,5);
$assert(count($mixed['follower'])===5,'A direct Follower role must not require a Follower judge panel.');
$assert(count($mixed['leader'])===8,'The judged Leader role must continue through the established calculation.');

echo "OK: role-specific direct Final and dynamic alternate rules preserve the scoring engine\n";
