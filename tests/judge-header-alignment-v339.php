<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$files=[
    'branding'=>$root.'/public/js/bdc-global-branding.js',
    'theme'=>$root.'/public/assets/css/bdc-theme.css',
    'test'=>$root.'/test-judge-scoring/index.php',
    'live'=>$root.'/judge-scoring/index.php',
];
foreach($files as $name=>$path){
    if(!is_file($path)){fwrite(STDERR,"Missing {$name}\n");exit(1);}
}
$branding=(string)file_get_contents($files['branding']);
$theme=(string)file_get_contents($files['theme']);
$test=(string)file_get_contents($files['test']);
$live=(string)file_get_contents($files['live']);
foreach(['judge-premium-header','judge-header-inner','judge-header-copy','judge-header-title','judge-header-meta','judge-header-chip','TEST ONLY'] as $marker){
    if(!str_contains($branding.$theme,$marker)){fwrite(STDERR,"Missing header marker {$marker}\n");exit(1);}
}
foreach(['judgeHeaderMeta','bdc-theme-control-judge','judgeHeaderMeta.appendChild(control)'] as $marker){
    if(!str_contains((string)file_get_contents($root.'/public/assets/js/bdc-theme.js'),$marker)){fwrite(STDERR,"Missing theme docking marker {$marker}\n");exit(1);}
}
foreach(['grid-template-columns:auto minmax(0,1fr)','max-width:390px','bdc-official-logo','overflow-wrap:anywhere'] as $marker){
    if(!str_contains($theme,$marker)){fwrite(STDERR,"Missing responsive marker {$marker}\n");exit(1);}
}
if(!str_contains($test,'bdc-theme.js?v=340')||!str_contains($live,'bdc-theme.js?v=340')){fwrite(STDERR,"Test/Live theme cache parity failed\n");exit(1);}
echo "judge header alignment v339: PASS\n";
