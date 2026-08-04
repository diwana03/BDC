<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Config;
use App\Core\Database;
use App\Services\BackupAutomationService;
use App\Services\SchemaUpdater;

header('Content-Type: application/json');
$expected=(string)Config::get('backup.cron_token','');
$provided=(string)($_GET['token']??'');
if($expected==='' || !hash_equals($expected,$provided)){
 http_response_code(403);echo json_encode(['ok'=>false,'error'=>'Invalid token']);exit;
}

try{
 $pdo=Database::connection();
 $automation=new BackupAutomationService(dirname(__DIR__,2));
 $result=$automation->run(false,null);
 echo json_encode(['ok'=>true,'result'=>$result],JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
 http_response_code(500);echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
