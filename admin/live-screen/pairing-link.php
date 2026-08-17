<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/bootstrap.php';

use App\Core\Auth;

Auth::requireAdmin();
$roundId = (int) ($_GET['round_id'] ?? $_POST['round_id'] ?? 0);
$test = ($_GET['data_mode'] ?? $_POST['data_mode'] ?? 'real') === 'test';
$query = http_build_query(['round_id' => $roundId, 'data_mode' => $test ? 'test' : 'real']);
header('Location: control.php?' . $query . '#emcee-match', true, 302);
exit;
