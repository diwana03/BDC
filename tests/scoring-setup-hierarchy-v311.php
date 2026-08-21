<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$automatic=(string)file_get_contents($root.'/admin/scoring/automatic-common-setup.php');
$manual=(string)file_get_contents($root.'/admin/scoring/core.php');
$test=(string)file_get_contents($root.'/admin/scoring-tests/index.php');
$premium=(string)file_get_contents($root.'/public/css/scoring-premium.css');

$registrationPosition=strpos($automatic,'Registration Desk <span');
$flightsPosition=strpos($automatic,'Flights <span');
$leadersPosition=strpos($automatic,"foreach(['leader'=>['Leaders'");
if($registrationPosition===false||$flightsPosition===false||$leadersPosition===false||$registrationPosition>$leadersPosition||$flightsPosition>$leadersPosition){
    fwrite(STDERR,"Registration Desk and Flights must appear before competitor entry.\n");exit(1);
}
foreach([$automatic,$manual] as $content){
    if(!str_contains($content,'border-warning border-2 bg-warning-subtle')){
        fwrite(STDERR,"Live Registration Desk must use the amber setup treatment.\n");exit(1);
    }
    if(!str_contains($content,'border-primary border-2 bg-primary-subtle')){
        fwrite(STDERR,"Live Flights must use the blue setup treatment.\n");exit(1);
    }
}
if(!str_contains($automatic,'role-card border-')||!str_contains($automatic,'text-white fw-semibold')){
    fwrite(STDERR,"Leader and Follower boards must retain distinct strong role colours.\n");exit(1);
}
if(!str_contains($test,'border-primary border-2 bg-primary-subtle')||!str_contains($test,'Manage Test Flights')){
    fwrite(STDERR,"Test Flights must match the Live blue hierarchy.\n");exit(1);
}
if(str_contains($automatic,'directly above without opening this link')){
    fwrite(STDERR,"Registration guidance still points in the old direction.\n");exit(1);
}
foreach(['#dcc487','#9eb7dd','#173b70','#72263a'] as $premiumColour){
    if(!str_contains($premium,$premiumColour)){
        fwrite(STDERR,"Premium scoring palette is incomplete.\n");exit(1);
    }
}
echo "Scoring setup hierarchy checks passed.\n";
