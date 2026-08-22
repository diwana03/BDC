<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$index=file_get_contents($root.'/admin/dance-cup/index.php');
$selector=file_get_contents($root.'/admin/dance-cup/select-mode.php');
$checks=[
    'selector is the default Dance Cup entry'=>str_contains($index,"require __DIR__.'/select-mode.php'"),
    'manual workflow is available'=>str_contains($selector,'Manual Scoring')&&str_contains($selector,'workflow=manual'),
    'automatic workflow is available'=>str_contains($selector,'Automatic Scoring')&&str_contains($selector,'workflow=automatic'),
    'projection workflow is available'=>str_contains($selector,'Live Projection')&&str_contains($selector,'workflow=projection'),
    'Test mode is retained'=>str_contains($selector,"\$test?'&data_mode=test':''"),
    'saved categories route by workflow'=>str_contains($index,"\$workflow==='automatic'?'automation.php'")&&str_contains($index,"\$workflow==='projection'?'projection-control.php':'category.php'"),
    'workflow is retained in dashboard forms'=>str_contains($index,'name="workflow"'),
    'workflow is retained after creating data'=>str_contains($index,"'&workflow='.rawurlencode(\$workflow)"),
    'workflow changer is visible'=>str_contains($index,'Change Dance Cup Workflow'),
    'Dance Cup remains separate from Jack and Jill'=>str_contains($selector,'separate from Jack &amp; Jill'),
];
foreach($checks as $label=>$ok){if(!$ok){fwrite(STDERR,"FAIL: {$label}\n");exit(1);}echo "PASS: {$label}\n";}
