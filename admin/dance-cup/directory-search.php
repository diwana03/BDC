<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Services\JudgeDirectoryService;

Auth::requireAdmin();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$term = trim((string) ($_GET['q'] ?? ''));
$type = (string) ($_GET['type'] ?? 'competitor');
$termLength = function_exists('mb_strlen') ? mb_strlen($term, 'UTF-8') : strlen($term);
if ($termLength < 1) {
    echo json_encode(['ok' => true, 'items' => []]);
    exit;
}

try {
    $pdo = Database::connection();
    if ($type === 'judge') {
        $rows = JudgeDirectoryService::search($pdo, $term, 100);
        $items = array_map(static fn(array $row): array => [
            'id' => (int) $row['id'],
            'code' => (string) ($row['judge_code'] ?? ''),
            'name' => (string) ($row['display_name'] ?: $row['full_name']),
            'meta' => trim(implode(' · ', array_filter([(string) ($row['judge_code'] ?? ''), (string) ($row['country'] ?? '')]))),
        ], $rows);
    } else {
        $query = $pdo->prepare("SELECT id,bdc_id,exact_name,country FROM bdc_competitors WHERE status<>'archived' AND (LOWER(exact_name) LIKE LOWER(:contains) OR LOWER(bdc_id) LIKE LOWER(:prefix)) ORDER BY CASE WHEN LOWER(exact_name) LIKE LOWER(:starts) THEN 0 ELSE 1 END,LOWER(exact_name),id LIMIT 100");
        $query->execute(['contains' => '%' . $term . '%', 'prefix' => $term . '%', 'starts' => $term . '%']);
        $items = array_map(static fn(array $row): array => [
            'id' => (int) $row['id'],
            'code' => (string) ($row['bdc_id'] ?? ''),
            'name' => (string) $row['exact_name'],
            'meta' => trim(implode(' · ', array_filter([(string) ($row['bdc_id'] ?? ''), (string) ($row['country'] ?? '')]))),
        ], $query->fetchAll());
    }
    echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    error_log('Dance Cup directory search failed: '.$error->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Directory search is temporarily unavailable.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
