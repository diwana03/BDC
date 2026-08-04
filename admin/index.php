<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\SchemaUpdater;

$error = '';
if (isset($_GET['logout'])) { Auth::logout(); header('Location: ./'); exit; }
if (!Auth::check()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) $error = 'Invalid security token. Refresh the page and try again.';
        elseif (!Auth::attempt((string)($_POST['email'] ?? ''),(string)($_POST['password'] ?? ''))) $error = 'Invalid email or password.';
        else { header('Location: ./'); exit; }
    }
    $csrfToken = Csrf::token(); require dirname(__DIR__) . '/app/Views/auth/login.php'; exit;
}
$pdo = Database::connection();

$stats = [
 'competitors'=>(int)$pdo->query('SELECT COUNT(*) FROM bdc_competitors')->fetchColumn(),
 'events'=>(int)$pdo->query('SELECT COUNT(*) FROM bdc_events')->fetchColumn(),
 'points'=>(int)$pdo->query('SELECT COUNT(*) FROM bdc_point_transactions')->fetchColumn(),
 'claims'=>(int)$pdo->query("SELECT COUNT(*) FROM bdc_claims WHERE status='pending'")->fetchColumn(),
 'profile_requests'=>(int)$pdo->query("SELECT COUNT(*) FROM bdc_profile_requests WHERE status IN ('pending','under_review','more_info')")->fetchColumn(),
];
$recentImports=$pdo->query('SELECT id,file_name,import_type,status,total_rows,imported_rows,error_rows,created_at FROM bdc_import_batches ORDER BY id DESC LIMIT 5')->fetchAll();
$recentEvents=$pdo->query("SELECT id,name,event_date,status FROM bdc_events ORDER BY COALESCE(event_date,'1900-01-01') DESC,id DESC LIMIT 5")->fetchAll();
$recentRegistrations=$pdo->query("SELECT r.id,r.full_name,r.created_at,r.registration_status,e.name AS event_name FROM bdc_event_registrations r JOIN bdc_events e ON e.id=r.event_id ORDER BY r.id DESC LIMIT 5")->fetchAll();
$stats['registrations']=(int)$pdo->query('SELECT COUNT(*) FROM bdc_event_registrations')->fetchColumn();
$stats['documents']=(int)$pdo->query("SELECT COUNT(*) FROM bdc_result_documents WHERE status='published'")->fetchColumn();
require dirname(__DIR__) . '/app/Views/admin/dashboard.php';
