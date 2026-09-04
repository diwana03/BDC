<?php
declare(strict_types=1);
ob_start();
require dirname(__DIR__, 2) . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Services\LiveDisplaySessionService;

Auth::requireAdmin();
$respond = static function (array $payload, int $status = 200): never {
    $leaked = (string) ob_get_clean();
    if ($leaked !== '') error_log('BDC projector controller suppressed output: ' . substr(strip_tags($leaked), 0, 500));
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
};
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::verify($_POST['_csrf'] ?? null)) $respond(['ok' => false, 'error' => 'Invalid request.'], 419);

$pdo = Database::connection();
$eventId = (int) ($_POST['event_id'] ?? 0);
$sessionId = (int) ($_POST['session_id'] ?? 0);
$test = ($_POST['data_mode'] ?? 'real') === 'test';
$userId = (int) (Auth::user()['id'] ?? 0);
if ($eventId < 1 && $sessionId < 1) {
    $roundTable = $test ? 'bdc_test_scoring_rounds' : 'bdc_scoring_rounds';
    $query = $pdo->prepare("SELECT event_id FROM {$roundTable} WHERE id=:r LIMIT 1");
    $query->execute(['r' => (int) ($_POST['round_id'] ?? 0)]);
    $eventId = (int) $query->fetchColumn();
}

try {
    $session = $sessionId > 0 ? LiveDisplaySessionService::byId($pdo, $sessionId, $test) : LiveDisplaySessionService::forEvent($pdo, $eventId, $test);
    if (!$session) throw new RuntimeException('Generate the Live Display link first.');
    $eventId = (int) $session['event_id'];
    $action = (string) ($_POST['action'] ?? 'update');
    $callbackReveal = $action === 'callback_reveal' || ($action === 'update' && (string) ($_POST['screen_type'] ?? '') === 'callbacks');

    if ($action === 'prepare_holding') $session = LiveDisplaySessionService::beginSelection($pdo, $eventId, (int) ($_POST['round_id'] ?? 0), $test, $userId);
    elseif ($action === 'unlock_results') $session = LiveDisplaySessionService::setResultsUnlocked($pdo, $eventId, $test, true, $userId);
    elseif ($action === 'lock_results') $session = LiveDisplaySessionService::setResultsUnlocked($pdo, $eventId, $test, false, $userId);
    elseif ($action === 'effect') $session = LiveDisplaySessionService::effect($pdo, $eventId, $test, (string) ($_POST['effect_type'] ?? 'none'), $userId, $sessionId);
    elseif ($callbackReveal) {
        $_POST['screen_type'] = 'callbacks';
        $_POST['effect_type'] = 'countdown';
        $session = LiveDisplaySessionService::update($pdo, $eventId, $test, $_POST, $userId, $sessionId);
    } elseif ($action === 'theme') $session = LiveDisplaySessionService::setTheme($pdo, $eventId, $test, (string) ($_POST['screen_theme'] ?? ''), $userId);
    elseif (str_starts_with($action, 'music_')) $session = LiveDisplaySessionService::musicControl($pdo, $eventId, $test, $action, (int) ($_POST['music_volume'] ?? 60), $userId);
    else $session = LiveDisplaySessionService::update($pdo, $eventId, $test, $_POST, $userId, $sessionId);

    $respond(['ok' => true, 'session' => [
        'screen_type' => $session['screen_type'] ?? 'holding', 'reveal_place' => $session['reveal_place'] ?? null,
        'effect_type' => $session['effect_type'] ?? null, 'effect_version' => (int) ($session['effect_version'] ?? 0),
        'screen_theme' => $session['screen_theme'] ?? 'midnight_burgundy', 'music_name' => $session['music_name'] ?? null,
        'music_status' => $session['music_status'] ?? 'stopped', 'music_volume' => (int) ($session['music_volume'] ?? 60),
        'music_version' => (int) ($session['music_version'] ?? 0), 'page_number' => (int) ($session['page_number'] ?? 1),
        'auto_page' => (bool) ($session['auto_page'] ?? false), 'page_delay_seconds' => (int) ($session['page_delay_seconds'] ?? 30),
        'loop_enabled' => (bool) ($session['loop_enabled'] ?? false), 'loop_screens' => $session['loop_screens'] ?? '',
        'loop_delay_seconds' => (int) ($session['loop_delay_seconds'] ?? 15), 'results_unlocked' => (bool) ($session['results_unlocked'] ?? false),
        'state_version' => (int) ($session['state_version'] ?? 0),
    ]]);
} catch (Throwable $exception) {
    $respond(['ok' => false, 'error' => $exception->getMessage()], 422);
}
