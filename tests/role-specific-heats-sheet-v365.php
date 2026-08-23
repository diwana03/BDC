<?php
declare(strict_types=1);

$root=dirname(__DIR__);
foreach(['Test'=>$root.'/admin/scoring-tests/index.php','Live'=>$root.'/admin/scoring/core.php'] as $surface=>$path){
    $source=(string)file_get_contents($path);
    foreach(['Role-specific direct Final:','Only the larger role appears on the Heats score sheet.',"if(\$roleAdvancementPlan[\$role]['direct_to_final']??false)continue",'&&!$allRolesDirect'] as $marker){
        if(!str_contains($source,$marker))throw new RuntimeException($surface.' role-specific Heats UI missing '.$marker);
    }
}
echo "OK: direct roles are announced and excluded from Test and Live manual Heats sheets\n";
