<?php
declare(strict_types=1);

use App\Services\UnapprovedProfileRepairService;

return [
    'dependencies'=>[
        dirname(__DIR__,2).'/app/Services/UnapprovedProfileRepairService.php',
        dirname(__DIR__,2).'/app/Services/BackupService.php',
    ],
    'up'=>static function(PDO $pdo):void{
        // One-time historical repair. The service creates a database backup,
        // protects every published result/points record and writes evidence
        // for each profile removed. A failure aborts the deployment migration.
        UnapprovedProfileRepairService::repair($pdo,0);
    },
];
