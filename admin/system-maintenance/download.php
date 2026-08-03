<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/bootstrap.php';
use App\Core\Auth;
use App\Services\BackupService;
Auth::requireAdmin();
try {
    $service = new BackupService(dirname(__DIR__, 2));
    $path = $service->resolve((string)($_GET['type'] ?? ''), (string)($_GET['name'] ?? ''));
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
} catch (Throwable $e) { http_response_code(404); echo 'Backup not found.'; }
