<?php
declare(strict_types=1);
$f=file_get_contents(__DIR__."/../live-display/feed.php");
if(!str_contains($f,"final-couple-person"))throw new RuntimeException("Final couple class missing");
if(!str_contains($f,"font-size:clamp(16px,1.18vw,28px)!important"))throw new RuntimeException("Final couple larger name missing");
if(!str_contains($f,"font-size:0!important"))throw new RuntimeException("Country text hide missing");
if(!str_contains($f,"$coupleColumns = 5;"))throw new RuntimeException("Five-card grid changed");
if(!str_contains($f,"competitor-country-name"))throw new RuntimeException("Separate Finalists screen was altered");
echo "finalist couples v655: PASS\n";
