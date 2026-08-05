<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit;}

$application=rtrim((string)($argv[1]??''),'/');
$destination=(string)($argv[2]??'');
if($application===''||!is_file($application.'/bootstrap.php')){
    fwrite(STDERR,"A valid Production application directory is required.\n");
    exit(1);
}
if($destination===''||!is_dir($destination)||!is_writable($destination)){
    fwrite(STDERR,"A writable deployment backup directory is required.\n");
    exit(1);
}

require $application.'/bootstrap.php';

$result=(new App\Services\BackupService())->createDatabaseBackup();
$source=$application.'/storage/backups/database/'.$result['name'];
$target=rtrim($destination,'/').'/'.$result['name'];
if(!copy($source,$target)){
    fwrite(STDERR,"Database backup could not be copied to the deployment backup.\n");
    exit(1);
}
echo 'Production database backed up: '.$target.PHP_EOL;
