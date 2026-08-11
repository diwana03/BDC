<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\HallOfFameService;

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$base = rtrim((string) App\Core\Config::get('app.base_path', '/portal'), '/');
if ($base !== '' && str_starts_with($path, $base)) {
    $path = substr($path, strlen($base));
}
$path = '/' . trim($path, '/');
if ($path === '//') {
    $path = '/';
}

if ($path === '/') {
    $user = Auth::user();
    $pdo = Database::connection();
    $latestEvents = [];
    $careerLeaders = [];
    try {
        $latestEvents = HallOfFameService::latest($pdo,3);
        $careerLeaders = $pdo->query("SELECT MIN(c.id) AS id, GROUP_CONCAT(DISTINCT c.bdc_id ORDER BY c.bdc_id SEPARATOR ' / ') AS bdc_id, COALESCE(g.display_name,MAX(c.exact_name)) AS exact_name, MAX(c.country) AS country, MAX(c.photo_url) AS photo_url, COALESCE(SUM(pt.points),0) AS career_points FROM bdc_competitors c JOIN bdc_point_transactions pt ON pt.competitor_id=c.id AND pt.dance_style='bachata' AND NOT EXISTS (SELECT 1 FROM bdc_point_transactions duplicate_pt JOIN bdc_competitors duplicate_c ON duplicate_c.id=duplicate_pt.competitor_id WHERE COALESCE(duplicate_c.career_group_id,-duplicate_c.id)=COALESCE(c.career_group_id,-c.id) AND duplicate_pt.dance_style=pt.dance_style AND duplicate_pt.event_id <=> pt.event_id AND duplicate_pt.division=pt.division AND duplicate_pt.dance_role=pt.dance_role AND COALESCE(LOWER(TRIM(duplicate_pt.placement)),'')=COALESCE(LOWER(TRIM(pt.placement)),'') AND duplicate_pt.points=pt.points AND duplicate_pt.id<pt.id) LEFT JOIN bdc_competitor_career_groups g ON g.id=c.career_group_id WHERE c.status='active' AND c.show_on_leaderboard=1 GROUP BY COALESCE(c.career_group_id,-c.id),g.display_name HAVING career_points > 0 ORDER BY career_points DESC,exact_name ASC LIMIT 10")->fetchAll();
    } catch (Throwable $e) {
        $latestEvents=[];
        $careerLeaders=[];
    }
    require __DIR__ . '/app/Views/public/home.php';
    exit;
}

if ($path === '/leaderboard') {
    require __DIR__ . '/leaderboard/index.php';
    exit;
}

if ($path === '/login') {
    if (Auth::check()) {
        header('Location: ' . url('/admin'));
        exit;
    }

    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            $error = 'Invalid security token.';
        } elseif (!Auth::attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
            $error = 'Invalid email or password.';
        } else {
            header('Location: ' . url('/admin'));
            exit;
        }
    }

    require __DIR__ . '/app/Views/auth/login.php';
    exit;
}

if ($path === '/logout') {
    Auth::logout();
    header('Location: ' . url('/login'));
    exit;
}

if ($path === '/admin') {
    Auth::requireAdmin();
    $pdo = Database::connection();

    $stats = [
        'competitors' => (int) $pdo->query('SELECT COUNT(*) FROM bdc_competitors')->fetchColumn(),
        'events' => (int) $pdo->query('SELECT COUNT(*) FROM bdc_events')->fetchColumn(),
        'points' => (int) $pdo->query('SELECT COUNT(*) FROM bdc_point_transactions')->fetchColumn(),
        'claims' => (int) $pdo->query("SELECT COUNT(*) FROM bdc_claims WHERE status='pending'")->fetchColumn(),
    ];

    $recentImports = $pdo->query(
        'SELECT file_name, import_type, status, created_at FROM bdc_import_batches ORDER BY id DESC LIMIT 5'
    )->fetchAll();

    $pendingCompetitionApprovals=[];
    if(method_exists(Auth::class,'isSuperAdmin') && Auth::isSuperAdmin()){
        $pendingCompetitionApprovals=$pdo->query("
            SELECT p.final_round_id,p.submitted_at,e.name event_name,e.event_date,r.division
            FROM bdc_scoring_publications p
            JOIN bdc_scoring_rounds r ON r.id=p.final_round_id
            JOIN bdc_events e ON e.id=p.event_id
            WHERE p.status='pending_approval'
            ORDER BY p.submitted_at ASC,p.id ASC
        ")->fetchAll();
    }

    require __DIR__ . '/app/Views/admin/dashboard.php';
    exit;
}

http_response_code(404);
require __DIR__ . '/app/Views/errors/404.php';
