<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$selector=file_get_contents($root.'/admin/dance-cup/select-mode.php');
$workflow=file_get_contents($root.'/admin/dance-cup/workflow.php');
$checks=[
    'manual selector opens real workflow dashboard'=>str_contains($selector,'workflow.php?workflow=manual'),
    'automatic selector opens real workflow dashboard'=>str_contains($selector,'workflow.php?workflow=automatic'),
    'projection selector opens real workflow dashboard'=>str_contains($selector,'workflow.php?workflow=projection'),
    'manual category action is direct'=>str_contains($workflow,"'manual'=>['Manual Scoring','category.php'"),
    'automatic category action is direct'=>str_contains($workflow,"'automatic'=>['Automatic Scoring','automation.php'"),
    'projection category action is direct'=>str_contains($workflow,"'projection'=>['Live Projection','projection-control.php'"),
    'Testing mode remains isolated'=>str_contains($workflow,"\$test?'&data_mode=test':''"),
    'management is a separate action'=>str_contains($workflow,'Manage Events &amp; Categories'),
    'Jack and Jill separation is explicit'=>str_contains($workflow,'Jack &amp; Jill remains separate'),
];
foreach($checks as $label=>$ok){if(!$ok){fwrite(STDERR,"FAIL: {$label}\n");exit(1);}echo "PASS: {$label}\n";}
