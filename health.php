<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$configPath = __DIR__ . '/config/config.php';
if (!is_file($configPath)) {
    http_response_code(503);
    echo json_encode(['status' => 'unavailable'], JSON_UNESCAPED_SLASHES);
    exit;
}
require __DIR__ . '/bootstrap.php';
$status = ['status' => 'unavailable'];
try {
    $pdo = App\Core\Database::connection();
    $pdo->query('SELECT 1');
    $installed=(bool)$pdo->query("SHOW TABLES LIKE 'bdc_users'")->fetchColumn();
    $status=['status'=>$installed?'ok':'unavailable'];
    http_response_code($installed?200:503);
} catch (Throwable $e) {
    $status=['status'=>'unavailable'];
    http_response_code(500);
}
echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
