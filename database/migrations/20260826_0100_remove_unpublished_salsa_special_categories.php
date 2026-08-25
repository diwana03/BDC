<?php
declare(strict_types=1);

use App\Services\UnapprovedProfileRepairService;

return [
    'dependencies'=>[
        dirname(__DIR__,2).'/app/Services/UnapprovedProfileRepairService.php',
        dirname(__DIR__,2).'/app/Services/SpecialCategoryRecoveryService.php',
        dirname(__DIR__,2).'/app/Services/BackupService.php',
    ],
    'up'=>static function(PDO $pdo):void{
        // Second pass includes unproven Salsa Open/Rising values. Genuine
        // manual, backup and official data-entry recovery evidence is protected.
        UnapprovedProfileRepairService::repair($pdo,0);
    },
];
