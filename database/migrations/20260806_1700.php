<?php
declare(strict_types=1);

use App\Services\SchemaUpdater;

return [
    'dependencies' => [dirname(__DIR__, 2) . '/app/Services/SchemaUpdater.php'],
    'up' => static function (PDO $pdo): void {
        SchemaUpdater::run($pdo);
    },
];
