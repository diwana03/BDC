<?php
declare(strict_types=1);
return static function(PDO $pdo):void{
    \App\Services\SystemTestRunService::ensureSchema($pdo);
};
