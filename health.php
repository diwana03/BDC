<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$configPath = __DIR__ . '/config/config.php';
if (!is_file($configPath)) {
    http_response_code(503);
    echo json_encode(['application' => 'BDC Portal', 'version' => '0.4.0', 'configuration' => 'missing', 'next_step' => 'Open setup.php'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}
require __DIR__ . '/bootstrap.php';
$status = ['application' => 'BDC Portal', 'version' => '0.4.0', 'php' => PHP_VERSION, 'database' => 'unknown', 'schema' => 'unknown', 'time' => date(DATE_ATOM)];
try {
    $pdo = App\Core\Database::connection();
    $pdo->query('SELECT 1');
    $status['database'] = 'connected';
    $status['schema'] = $pdo->query("SHOW TABLES LIKE 'bdc_users'")->fetchColumn() ? 'installed' : 'not_installed';
    http_response_code(200);
} catch (Throwable $e) {
    $status['database'] = 'failed';
    $status['schema'] = 'unavailable';
    $status['error'] = $e->getMessage();
    http_response_code(500);
}
echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
