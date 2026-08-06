<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;

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
        $events = $pdo->query("SELECT id,name,event_date,location,venue FROM bdc_events WHERE status IN ('published','completed') AND event_date IS NOT NULL AND EXISTS (SELECT 1 FROM bdc_participant_results pr WHERE pr.event_id=bdc_events.id AND CAST(pr.placement AS UNSIGNED) BETWEEN 1 AND 3) ORDER BY event_date DESC,id DESC LIMIT 3")->fetchAll();
        $resultStmt = $pdo->prepare("SELECT pr.placement,pr.dance_role,pr.partner_name,c.id competitor_id,c.exact_name,c.photo_url FROM bdc_participant_results pr JOIN bdc_competitors c ON c.id=pr.competitor_id WHERE pr.event_id=:event_id AND CAST(pr.placement AS UNSIGNED) BETWEEN 1 AND 3 ORDER BY CAST(pr.placement AS UNSIGNED),FIELD(pr.dance_role,'leader','follower')");
        $careerLeaders = $pdo->query("SELECT MIN(c.id) AS id, GROUP_CONCAT(DISTINCT c.bdc_id ORDER BY c.bdc_id SEPARATOR ' / ') AS bdc_id, COALESCE(g.display_name,MAX(c.exact_name)) AS exact_name, MAX(c.country) AS country, MAX(c.photo_url) AS photo_url, COALESCE(SUM(pt.points),0) AS career_points FROM bdc_competitors c JOIN bdc_point_transactions pt ON pt.competitor_id=c.id AND NOT EXISTS (SELECT 1 FROM bdc_point_transactions duplicate_pt JOIN bdc_competitors duplicate_c ON duplicate_c.id=duplicate_pt.competitor_id WHERE COALESCE(duplicate_c.career_group_id,-duplicate_c.id)=COALESCE(c.career_group_id,-c.id) AND duplicate_pt.event_id <=> pt.event_id AND duplicate_pt.division=pt.division AND duplicate_pt.dance_role=pt.dance_role AND COALESCE(LOWER(TRIM(duplicate_pt.placement)),'')=COALESCE(LOWER(TRIM(pt.placement)),'') AND duplicate_pt.points=pt.points AND duplicate_pt.id<pt.id) LEFT JOIN bdc_competitor_career_groups g ON g.id=c.career_group_id WHERE c.status='active' AND c.show_on_leaderboard=1 GROUP BY COALESCE(c.career_group_id,-c.id),g.display_name HAVING career_points > 0 ORDER BY career_points DESC,exact_name ASC LIMIT 10")->fetchAll();
        foreach ($events as $event) {
            $resultStmt->execute(['event_id'=>$event['id']]);
            $placements = [];
            foreach ($resultStmt->fetchAll() as $r) {
                $place=(int)$r['placement'];
                if (!isset($placements[$place])) $placements[$place]=['leader'=>null,'follower'=>null];
                if (in_array($r['dance_role'],['leader','follower'],true)) $placements[$place][$r['dance_role']]=$r;
            }
            $event['placements']=$placements;
            $latestEvents[]=$event;
        }
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
