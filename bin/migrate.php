<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

use App\Core\Database;
use App\Services\MigrationRunner;

try {
    require dirname(__DIR__) . '/bootstrap.php';

    $root = dirname(__DIR__);
    fwrite(STDOUT, '[MIGRATION] PHP ' . PHP_VERSION . ' · root ' . $root . PHP_EOL);

    $runner = new MigrationRunner(Database::connection(), $root . '/database/migrations');
    $completed = $runner->run();
    echo $completed ? 'Applied: ' . implode(', ', $completed) . PHP_EOL : 'Database is up to date.' . PHP_EOL;
} catch (Throwable $e) {
    /*
     * Keep this diagnostic intentionally short so DeploymentPipelineService::runProcess()
     * includes the complete failure in its last-eight-lines error summary. Do not print
     * connection strings, passwords, request data or a full stack trace into release logs.
     */
    fwrite(STDERR, '[MIGRATION_FATAL] ' . get_class($e) . ': ' . $e->getMessage() . PHP_EOL);
    fwrite(STDERR, '[MIGRATION_FATAL] File: ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL);
    fwrite(STDERR, '[MIGRATION_FATAL] PHP: ' . PHP_VERSION . ' · Root: ' . dirname(__DIR__) . PHP_EOL);
    exit(255);
}
