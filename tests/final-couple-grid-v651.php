<?php
declare(strict_types=1);
$feed=file_get_contents(__DIR__.'/../live-display/feed.php');
if(!str_contains($feed,'$coupleColumns = 5;'))throw new RuntimeException('Couple grid is not locked to the standard five-card row');
if(!str_contains($feed,'justify-content:center'))throw new RuntimeException('Incomplete couple rows are not centered');
if(!str_contains($feed,'flex:0 0 calc('))throw new RuntimeException('Couple cards can still stretch on incomplete rows');
if(str_contains($feed,'flex:1 1 calc(<?=number_format(100/$coupleColumns'))throw new RuntimeException('Old stretching couple rule still present');
if(!str_contains($feed,'BDC · Official Live Display'))throw new RuntimeException('Official Live Display badge missing');
if(!str_contains($feed,"url('public/assets/bdc-logo.png')"))throw new RuntimeException('Official BDC logo reference missing');
echo "final couple grid v651: PASS\n";
