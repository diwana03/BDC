<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;
Auth::requireSuperAdmin();
header('Content-Type: application/json');
http_response_code(410);
echo json_encode([
 'ok'=>false,
 'error'=>'Browser PDF upload is no longer required. Repository publication now uses the reviewed HTML result pages.'
]);
