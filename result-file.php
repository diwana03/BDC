<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';

use App\Core\Database;
use App\Services\ResultStorageService;

$name=basename((string)($_GET['file']??''));
if($name==='' || $name==='.' || $name==='..'){http_response_code(404);exit;}

$storage=ResultStorageService::relative($name);
$stmt=Database::connection()->prepare("SELECT 1 FROM bdc_result_documents WHERE storage_path=:path AND status='published' LIMIT 1");
$stmt->execute(['path'=>$storage]);
if(!$stmt->fetchColumn()){http_response_code(404);exit;}

$path=ResultStorageService::resolve($storage);
if(!$path){http_response_code(404);exit;}
$ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));
$types=['pdf'=>'application/pdf','html'=>'text/html; charset=UTF-8','csv'=>'text/csv; charset=UTF-8'];
if(!isset($types[$ext])){http_response_code(403);exit;}

header('Content-Type: '.$types[$ext]);
header('Content-Length: '.filesize($path));
header('Content-Disposition: inline; filename="'.str_replace('"','',basename($path)).'"');
header('Cache-Control: public, max-age=300, nosniff');
readfile($path);
