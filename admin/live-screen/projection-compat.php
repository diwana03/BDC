<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Database;
use App\Services\LiveDisplaySessionService;

require dirname(__DIR__, 2) . '/bootstrap.php';

$test = ($_GET['data_mode'] ?? 'real') === 'test';
$roundId = (int) ($_GET['round_id'] ?? 0);
$displayToken = trim((string) ($_GET['display_token'] ?? ''));
$requestedType = trim((string) ($_GET['type'] ?? ''));

if ($displayToken !== '') {
    $pdo = Database::connection();
    $session = LiveDisplaySessionService::byToken($pdo, $displayToken);
    $expectedMode = $test ? 'test' : 'real';
    if (
        !$session
        || (string) $session['data_mode'] !== $expectedMode
        || (int) ($session['current_round_id'] ?? 0) !== $roundId
        || ($requestedType !== '' && (string) $session['screen_type'] !== $requestedType)
    ) {
        http_response_code(403);
        exit('Projection state changed.');
    }
    header('Location: ' . url('live-display/?token=' . rawurlencode($displayToken)), true, 302);
    exit;
}

Auth::requireAdmin();
$query = http_build_query([
    'round_id' => $roundId,
    'data_mode' => $test ? 'test' : 'real',
    'selection' => 1,
]);
header('Location: control.php?' . $query, true, 302);
exit;
