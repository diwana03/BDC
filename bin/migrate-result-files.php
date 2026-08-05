<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';

use App\Core\Database;
use App\Services\ResultStorageService;

if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);
$pdo=Database::connection();
$rows=$pdo->query("SELECT id,storage_path FROM bdc_result_documents WHERE storage_path IS NOT NULL AND storage_path<>'' AND storage_path NOT LIKE 'protected-results://%'")->fetchAll();
$moved=0;$missing=0;
foreach($rows as $row){
    $relative=ltrim(str_replace('\\','/',(string)$row['storage_path']),'/');
    $source=$root.'/'.$relative;
    if(!is_file($source)){$missing++;continue;}
    $name=basename($source);
    $target=ResultStorageService::path($name);
    if(is_file($target)){$name=pathinfo($name,PATHINFO_FILENAME).'-'.(int)$row['id'].'.'.pathinfo($name,PATHINFO_EXTENSION);$target=ResultStorageService::path($name);}
    if(!rename($source,$target))throw new RuntimeException('Could not move '.$source);
    @chmod($target,0640);
    $storage=ResultStorageService::relative($name);
    $url=ResultStorageService::publicUrl($name);
    $update=$pdo->prepare('UPDATE bdc_result_documents SET storage_path=:storage,url=:url,updated_at=NOW() WHERE id=:id');
    $update->execute(['storage'=>$storage,'url'=>$url,'id'=>(int)$row['id']]);
    $moved++;
}
echo "Moved {$moved} result file(s); {$missing} database record(s) had no local file.\n";
