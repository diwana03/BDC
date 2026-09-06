<?php
declare(strict_types=1);
$feed=file_get_contents(__DIR__.'/../live-display/feed.php');
if(!str_contains($feed,'(string)$r["round_type"]==="final"?"JUDGING ALL COUPLES"'))throw new RuntimeException('Final judge label missing');
if(!str_contains($feed,'"JUDGING LEADERS":($scope==="follower"?"JUDGING FOLLOWERS":"JUDGING LEADERS & FOLLOWERS")'))throw new RuntimeException('Heats judge wording changed');
if(!str_contains($feed,'padding-bottom:clamp(6px,.55vh,12px)'))throw new RuntimeException('Couple bottom spacing missing');
if(!str_contains($feed,'$coupleColumns = 5;'))throw new RuntimeException('Responsive five-card couple grid changed');
if(!str_contains($feed,'BDC · Official Live Display'))throw new RuntimeException('Official live badge changed');
if(!str_contains($feed,"public/assets/bdc-logo.png"))throw new RuntimeException('BDC logo changed');
echo "final projector polish v652: PASS\n";
