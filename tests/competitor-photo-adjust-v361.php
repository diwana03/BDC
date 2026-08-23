<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$adjust=file_get_contents($root.'/admin/competitors/photo-adjust.php');
$edit=file_get_contents($root.'/admin/competitors/edit.php');
$failures=[];

foreach([
 'replacement upload'=>'name="replacement_photo"',
 'replacement action'=>'name="action" value="replace"',
 'new original preservation'=>'original_photo_url=:original',
 'touch drag protection'=>'touch-action:none',
 'native image drag protection'=>'draggable="false"',
 'smooth rendering'=>'requestAnimationFrame',
 'cancelled gesture recovery'=>"'pointercancel'",
 'lost gesture recovery'=>"'lostpointercapture'",
 'frame boundary clamp'=>'Math.max(-maxX',
] as $label=>$needle)if(!str_contains($adjust,$needle))$failures[]='Missing '.$label.'.';

if(!str_contains($edit,'Adjust or replace photo'))$failures[]='Edit Competitor is missing the photo adjustment action.';
if(!str_contains($edit,'SET original_photo_url=:photo'))$failures[]='Edit Competitor does not preserve a newly uploaded original.';
if(!str_contains($edit,'SET original_photo_url=NULL'))$failures[]='Removing a photo does not clear the preserved original.';

if($failures){fwrite(STDERR,implode(PHP_EOL,$failures).PHP_EOL);exit(1);}
echo "Competitor photo adjustment v361 checks passed.\n";
