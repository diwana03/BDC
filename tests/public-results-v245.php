<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$results=(string)file_get_contents($root.'/results/index.php');
$competitor=(string)file_get_contents($root.'/competitor/index.php');

if(!str_contains($results,"r.points_awarded>0")){
 throw new RuntimeException('Public Participant Results still expose zero-point placements.');
}
if(!str_contains($competitor,"r.points_awarded>0")){
 throw new RuntimeException('Public competitor history still exposes zero-point participant results.');
}
if(!str_contains($competitor,"r.points>0")){
 throw new RuntimeException('Legacy public competitor history fallback still exposes zero-point transactions.');
}

echo "Public zero-point result filters verified.\n";
