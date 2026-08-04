<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/bootstrap.php';

use App\Services\BackupService;

$destination=$argv[1]??'';
if($destination===''||!is_dir($destination)||!is_writable($destination)){
    fwrite(STDERR,"A writable deployment backup directory is required.\n");
    exit(1);
}

$result=(new BackupService())->createDatabaseBackup();
$source=dirname(__DIR__).'/storage/backups/database/'.$result['name'];
$target=rtrim($destination,'/').'/'.$result['name'];
if(!copy($source,$target)){
    fwrite(STDERR,"Database backup could not be copied to the deployment backup.\n");
    exit(1);
}
echo 'Production database backed up: '.$target.PHP_EOL;
