<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/bootstrap.php';

use App\Core\Database;
use App\Services\DeploymentPipelineService;
use App\Services\ReleaseManagerService;

if(!ReleaseManagerService::isReleaseManagerAvailable()){
    fwrite(STDERR,"Release Manager worker is available only on Staging.\n");
    exit(1);
}

$lock=fopen(sys_get_temp_dir().'/bdc-deployment-worker.lock','c');
if($lock===false||!flock($lock,LOCK_EX|LOCK_NB))exit(0);

$pdo=Database::connection();
$job=DeploymentPipelineService::nextJob($pdo);
if(!$job){echo "No queued deployment.\n";exit(0);}

try{
    DeploymentPipelineService::execute($pdo,$job);
    echo 'Deployment job '.$job['id']." completed.\n";
}catch(Throwable $e){
    fwrite(STDERR,'Deployment job '.$job['id'].' failed: '.$e->getMessage()."\n");
    exit(1);
}
