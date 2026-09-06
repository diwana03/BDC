<?php
declare(strict_types=1);
$feed=file_get_contents(__DIR__.'/../live-display/feed.php');
if(!str_contains($feed,'ProjectionNameService::firstName((string)$x["display_name"])'))throw new RuntimeException('First-name projector rendering missing');
if(!str_contains($feed,'font-size:clamp(16px,1.35vw,30px)'))throw new RuntimeException('Larger responsive competitor name missing');
if(!str_contains($feed,'width:clamp(28px,2.05vw,44px)'))throw new RuntimeException('Larger competitor flag missing');
if(str_contains($feed,'competitor-country-name'))throw new RuntimeException('Country text still present on competitor cards');
if(!str_contains($feed,'white-space:nowrap'))throw new RuntimeException('Single-line protection missing');
if(!str_contains($feed,'competitor-bib'))throw new RuntimeException('Bib rendering missing');
echo "projector name flag v653: PASS\n";
