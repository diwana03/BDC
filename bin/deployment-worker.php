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

    /*
     * Production must never be reported as successful unless the files that are
     * physically installed at the configured Production path identify the exact
     * commit that this job was asked to deploy. The normal health check proves
     * the site answers, but it does not prove that rsync updated the intended
     * application root; an older healthy portal could otherwise create a false
     * success result.
     */
    if(($job['target_environment']??'')==='production'){
        $settings=DeploymentPipelineService::settings();
        $productionPath=rtrim((string)($settings['production_path']??''),'/');
        $expectedSha=(string)($job['commit_sha']??'');
        $installed=ReleaseManagerService::installedVersion($productionPath);
        $actualSha=(string)($installed['commit_sha']??'');
        $actualVersion=(string)($installed['version']??'unknown');
        $actualEnvironment=(string)($installed['environment']??'');

        if($installed===null
            ||$actualSha===''||!hash_equals($expectedSha,$actualSha)
            ||$actualEnvironment!=='production'){
            $detail='Production verification failed after deployment. Expected commit '
                .$expectedSha.', but configured target '.$productionPath
                .' reports version '.$actualVersion.', commit '.($actualSha!==''?$actualSha:'missing')
                .', environment '.($actualEnvironment!==''?$actualEnvironment:'missing').'.';

            $stmt=$pdo->prepare("UPDATE bdc_deployment_jobs
                SET status='failed',completed_at=NOW(),
                    output=CONCAT(COALESCE(output,''),IF(COALESCE(output,'')='','',CHAR(10)),:detail)
                WHERE id=:id");
            $stmt->execute(['detail'=>$detail,'id'=>$job['id']]);
            $pdo->prepare("UPDATE bdc_release_candidates SET status='approved' WHERE id=:id")
                ->execute(['id'=>$job['release_id']]);
            throw new RuntimeException($detail);
        }

        $verified='[PRODUCTION_TARGET_VERIFIED] '.$actualVersion.' at commit '.$actualSha
            .' is installed at '.$productionPath.'.';
        $stmt=$pdo->prepare("UPDATE bdc_deployment_jobs
            SET output=CONCAT(COALESCE(output,''),IF(COALESCE(output,'')='','',CHAR(10)),:verified)
            WHERE id=:id");
        $stmt->execute(['verified'=>$verified,'id'=>$job['id']]);
    }

    echo 'Deployment job '.$job['id']." completed.\n";
}catch(Throwable $e){
    fwrite(STDERR,'Deployment job '.$job['id'].' failed: '.$e->getMessage()."\n");
    exit(1);
}
