<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$bootstrap=file_get_contents($root.'/bootstrap.php');
$automatic=file_get_contents($root.'/admin/scoring/automatic-common-setup.php');
$final=file_get_contents($root.'/admin/scoring/core.php');

if(!str_contains((string)$bootstrap,'if(window.self!==window.top)return;')){
    fwrite(STDERR,"Universal admin navigation must stop inside iframes.\n");exit(1);
}
foreach([$automatic,$final] as $content){
    if(!str_contains((string)$content,'judge_panel=1')||!str_contains((string)$content,'iframe')){
        fwrite(STDERR,"Embedded judge-control marker missing.\n");exit(1);
    }
}
if(!str_contains((string)$automatic,'target="_blank"')||!str_contains((string)$final,'target="_blank"')){
    fwrite(STDERR,"Standalone judge control must remain available.\n");exit(1);
}
echo "Iframe navigation checks passed.\n";
