<?php
declare(strict_types=1);

use App\Services\SpecialCategoryRecoveryService;

return [
    'dependencies'=>[dirname(__DIR__,2).'/app/Services/SpecialCategoryRecoveryService.php'],
    'up'=>static function(PDO $pdo):void{SpecialCategoryRecoveryService::ensureSchema($pdo);},
];
