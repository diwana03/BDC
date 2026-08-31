<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
use App\Core\Auth;use App\Core\Database;use App\Services\ProfileIntegrationService;
Auth::requireAdmin();$photo=ProfileIntegrationService::stagedPhoto(Database::connection(),(int)($_GET['id']??0));if(!$photo){http_response_code(404);exit('Photo not found.');}header('Content-Type: '.$photo['mime']);header('Content-Length: '.filesize($photo['path']));header('Cache-Control: private, no-store');header('Content-Disposition: inline');readfile($photo['path']);
