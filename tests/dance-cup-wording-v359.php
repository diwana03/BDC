<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$selector=file_get_contents($root.'/admin/dance-cup/select-mode.php');
$workflow=file_get_contents($root.'/admin/dance-cup/workflow.php');
$checks=[
    'technical change-workflow label removed'=>!str_contains($workflow,'Change Workflow'),
    'clear scoring-options label present'=>str_contains($workflow,'← Scoring Options'),
    'event and category heading present'=>str_contains($workflow,'Events &amp; Categories'),
    'short management label present'=>str_contains($workflow,'Events &amp; Categories'),
    'repeated separation sentence removed'=>!str_contains($workflow,'Only Dance Cup categories are listed'),
    'hero title contrast fixed'=>str_contains($workflow,'.workflow-hero h1,.workflow-hero p{color:#fff}'),
    'selector copy simplified'=>str_contains($selector,'Select scoring option.'),
    'Testing mode remains retained'=>str_contains($workflow,"\$test?'&data_mode=test':''"),
];
foreach($checks as $label=>$ok){if(!$ok){fwrite(STDERR,"FAIL: {$label}\n");exit(1);}echo "PASS: {$label}\n";}
