<?php
declare(strict_types=1);

$root=dirname(__DIR__);
foreach(['Live'=>$root.'/admin/scoring/core.php','Test'=>$root.'/admin/scoring-tests/index.php'] as $surface=>$path){
    $source=(string)file_get_contents($path);
    if(!str_contains($source,"'Automatic Final Judge Scoring':'manual Relative Placement scoring'")){
        throw new RuntimeException($surface.' Final pairing prompt does not respect the scoring workflow.');
    }
}

$display=(string)file_get_contents($root.'/live-display/index.php');
foreach(['260-particles.length','rockets.length<2','t-lastFxPaint<42','const d=1','},1400)'] as $guard){
    if(!str_contains($display,$guard))throw new RuntimeException('Projector fireworks performance guard missing: '.$guard);
}
if(str_contains($display,'520-particles.length')||str_contains($display,'rockets.length<5')){
    throw new RuntimeException('Heavy sustained-fireworks limits are still active.');
}

echo "Automatic Final routing and projector performance checks passed.\n";
