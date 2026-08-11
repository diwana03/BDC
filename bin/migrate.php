<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;
use App\Services\MigrationRunner;

$runner = new MigrationRunner(Database::connection(), dirname(__DIR__) . '/database/migrations');
$completed = $runner->run();
echo $completed ? 'Applied: ' . implode(', ', $completed) . PHP_EOL : 'Database is up to date.' . PHP_EOL;
