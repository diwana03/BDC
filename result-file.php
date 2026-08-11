<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require __DIR__.'/app/Support/result_repository_links.php';

use App\Core\Database;
use App\Services\ResultStorageService;

$name=repository_safe_result_basename((string)($_GET['file']??''));
if($name===null){http_response_code(404);exit;}

/*
 * Authorize the requested filename against a published BDC-managed repository
 * record. Historical rows may still contain legacy portal paths or URLs; those
 * values are used only to identify the filename. The actual bytes are always
 * served from ResultStorageService for the CURRENT environment.
 */
$stmt=Database::connection()->query("SELECT storage_path,url FROM bdc_result_documents WHERE status='published'");
$authorized=false;
foreach($stmt->fetchAll() as $document){
    $storagePath=trim((string)($document['storage_path']??''));
    $storedUrl=trim((string)($document['url']??''));
    $isManaged=$storagePath!==''||repository_is_bdc_managed_url($storedUrl);
    if(!$isManaged)continue;
    $documentName=repository_managed_document_filename($storagePath,$storedUrl);
    if($documentName!==null&&hash_equals($documentName,$name)){
        $authorized=true;
        break;
    }
}
if(!$authorized){http_response_code(404);exit;}

$path=ResultStorageService::resolveFilename($name);
if(!$path){http_response_code(404);exit;}
$ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));
$types=['pdf'=>'application/pdf','html'=>'text/html; charset=UTF-8','csv'=>'text/csv; charset=UTF-8'];
if(!isset($types[$ext])){http_response_code(403);exit;}

header('Content-Type: '.$types[$ext]);
header('Content-Length: '.filesize($path));
header('Content-Disposition: inline; filename="'.str_replace('"','',basename($path)).'"');
header('Cache-Control: public, max-age=300, nosniff');
readfile($path);
