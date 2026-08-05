<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use PDO;
use RuntimeException;

final class DeploymentPipelineService
{
    private const SHA_PATTERN='/^[a-f0-9]{40}$/';

    public static function settings():array
    {
        $settings=(array)Config::get('deployment',[]);
        return [
            'enabled'=>(bool)($settings['enabled']??false),
            'repository_path'=>(string)($settings['repository_path']??''),
            'source_branch'=>(string)($settings['source_branch']??'develop'),
            'staging_path'=>(string)($settings['staging_path']??''),
            'production_path'=>(string)($settings['production_path']??''),
            'backup_path'=>(string)($settings['backup_path']??''),
            'staging_health_url'=>(string)($settings['staging_health_url']??''),
            'production_health_url'=>(string)($settings['production_health_url']??''),
        ];
    }

    public static function discover(PDO $pdo):array
    {
        $config=self::settings();
        self::assertEnabled($config);
        self::runGit($config['repository_path'],['fetch','--quiet','origin',$config['source_branch']]);
        $lines=self::runGit($config['repository_path'],[
            'log','--format=%H%x09%s','--max-count=50','origin/'.$config['source_branch'],
        ]);
        $insert=$pdo->prepare("INSERT IGNORE INTO bdc_release_candidates(commit_sha,version,source_branch,subject)
            VALUES(:sha,:version,:branch,:subject)");
        foreach(preg_split('/\R/',trim($lines))?:[] as $line){
            if($line==='')continue;
            [$sha,$subject]=array_pad(explode("\t",$line,2),2,'');
            if(!preg_match(self::SHA_PATTERN,$sha))continue;
            $insert->execute([
                'sha'=>$sha,
                'version'=>self::versionForCommit($config['repository_path'],$sha),
                'branch'=>$config['source_branch'],
                'subject'=>mb_substr($subject,0,500),
            ]);
        }
        return $config;
    }

    public static function latestSourceSha():string
    {
        $config=self::settings();
        self::assertEnabled($config);
        $sha=trim(self::runGit($config['repository_path'],['rev-parse','origin/'.$config['source_branch']]));
        if(!preg_match(self::SHA_PATTERN,$sha))throw new RuntimeException('The latest source release could not be identified.');
        return $sha;
    }

    public static function queue(PDO $pdo,int $releaseId,string $action,int $userId):int
    {
        self::recoverStaleJobs($pdo);
        $release=self::release($pdo,$releaseId);
        $allowed=[
            'deploy_staging'=>['new','failed','passed'],
            'approve'=>['passed'],
            'deploy_production'=>['approved'],
        ];
        if(!isset($allowed[$action])||!in_array($release['status'],$allowed[$action],true)){
            throw new RuntimeException('This action is not allowed for the selected release status.');
        }
        if($action==='deploy_production'&&!hash_equals((string)$release['commit_sha'],(string)$release['staging_tested_sha'])){
            throw new RuntimeException('Production is blocked because this exact commit did not pass Staging.');
        }
        if($action==='approve'){
            $pdo->prepare("UPDATE bdc_release_candidates SET status='approved',approved_at=NOW(),approved_by=:uid WHERE id=:id AND status='passed'")
                ->execute(['uid'=>$userId,'id'=>$releaseId]);
            return 0;
        }
        $target=$action==='deploy_staging'?'staging':'production';
        $busy=$pdo->query("SELECT COUNT(*) FROM bdc_deployment_jobs WHERE status IN ('queued','running')")->fetchColumn();
        if((int)$busy>0)throw new RuntimeException('Another deployment is already queued or running.');
        $stmt=$pdo->prepare("INSERT INTO bdc_deployment_jobs(release_id,action,target_environment,commit_sha,requested_by,output)
            VALUES(:release_id,:action,:target,:sha,:uid,:output)");
        $stmt->execute([
            'release_id'=>$releaseId,
            'action'=>$action,
            'target'=>$target,
            'sha'=>$release['commit_sha'],
            'uid'=>$userId,
            'output'=>'Queued from the web Release Manager at '.date(DATE_ATOM).'.',
        ]);
        $pdo->prepare("UPDATE bdc_release_candidates SET status='queued' WHERE id=:id")->execute(['id'=>$releaseId]);
        return (int)$pdo->lastInsertId();
    }

    public static function startWorker(PDO $pdo,int $jobId=0):int
    {
        $config=self::settings();
        self::assertEnabled($config);
        $script=dirname(__DIR__,2).'/bin/deployment-worker.php';
        if(!is_file($script))throw new RuntimeException('The deployment worker script is missing.');

        if($jobId>0){
            $stmt=$pdo->prepare("SELECT status FROM bdc_deployment_jobs WHERE id=:id");
            $stmt->execute(['id'=>$jobId]);
            $status=$stmt->fetchColumn();
            if($status==='running')return $jobId;
            if($status!=='queued')throw new RuntimeException('The selected deployment is not waiting or running. Refresh the page for its current status.');
        }else{
            $jobId=(int)$pdo->query("SELECT id FROM bdc_deployment_jobs WHERE status='queued' ORDER BY id LIMIT 1")->fetchColumn();
            if($jobId<1)return 0;
        }

        $php=PHP_BINDIR.'/php';
        if(!is_file($php)||!is_executable($php))$php='php';
        $logDir=rtrim($config['backup_path'],'/');
        if(!is_dir($logDir)&&!mkdir($logDir,0700,true))throw new RuntimeException('The deployment worker log directory cannot be created.');
        $log=$logDir.'/deployment-worker.log';
        $command='nohup '.escapeshellarg($php).' '.escapeshellarg($script)
            .' >> '.escapeshellarg($log).' 2>&1 < /dev/null & echo $!';
        $lines=[];
        exec($command,$lines,$code);
        $pid=(int)trim((string)($lines[0]??''));
        if($code!==0||$pid<1)throw new RuntimeException('The web server could not start the deployment worker.');

        $stmt=$pdo->prepare("UPDATE bdc_deployment_jobs
            SET output=CONCAT(COALESCE(output,''),IF(COALESCE(output,'')='','',CHAR(10)),:line)
            WHERE id=:id AND status='queued'");
        $stmt->execute(['line'=>'Background worker started from the web dashboard (process '.$pid.').','id'=>$jobId]);
        return $jobId;
    }

    public static function nextJob(PDO $pdo):?array
    {
        self::recoverStaleJobs($pdo);
        $pdo->beginTransaction();
        try{
            $job=$pdo->query("SELECT * FROM bdc_deployment_jobs WHERE status='queued' ORDER BY id LIMIT 1 FOR UPDATE")->fetch();
            if(!$job){$pdo->commit();return null;}
            $pdo->prepare("UPDATE bdc_deployment_jobs SET status='running',started_at=NOW() WHERE id=:id")
                ->execute(['id'=>$job['id']]);
            $pdo->prepare("UPDATE bdc_release_candidates SET status='testing' WHERE id=:id")
                ->execute(['id'=>$job['release_id']]);
            $pdo->commit();
            return $job;
        }catch(\Throwable $e){$pdo->rollBack();throw $e;}
    }

    public static function runQueuedJob(PDO $pdo,int $jobId):void
    {
        $pdo->beginTransaction();
        try{
            $stmt=$pdo->prepare("SELECT * FROM bdc_deployment_jobs WHERE id=:id AND status='queued' FOR UPDATE");
            $stmt->execute(['id'=>$jobId]);
            $job=$stmt->fetch();
            if(!$job)throw new RuntimeException('The deployment is no longer waiting to run.');
            $pdo->prepare("UPDATE bdc_deployment_jobs SET status='running',started_at=NOW() WHERE id=:id")
                ->execute(['id'=>$jobId]);
            $pdo->prepare("UPDATE bdc_release_candidates SET status='testing' WHERE id=:id")
                ->execute(['id'=>$job['release_id']]);
            $pdo->commit();
        }catch(\Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }
        self::execute($pdo,$job);
    }

    public static function execute(PDO $pdo,array $job):void
    {
        $existingOutput=trim((string)($job['output']??''));
        $output=$existingOutput===''?[]:(preg_split('/\R/',$existingOutput)?:[]);
        $output[]='Deployment worker began processing at '.date(DATE_ATOM).'.';
        $productionBackup=null;
        $config=[];
        try{
            $config=self::settings();
            self::assertEnabled($config);
            $sha=(string)$job['commit_sha'];
            if(!preg_match(self::SHA_PATTERN,$sha))throw new RuntimeException('Invalid release commit.');
            $target=$job['target_environment']==='staging'?$config['staging_path']:$config['production_path'];
            self::assertSafeTarget($target);
            self::runGit($config['repository_path'],['fetch','--quiet','origin',$config['source_branch']]);
            self::runGit($config['repository_path'],['cat-file','-e',$sha.'^{commit}']);
            if($job['target_environment']==='production'){
                $productionBackup=self::backupProduction($config,$sha,$output);
            }
            self::deployTree($config['repository_path'],$sha,$target,$output);
            self::writeReleaseManifest(
                $target,
                self::versionForCommit($config['repository_path'],$sha),
                $sha,
                (string)$job['target_environment']
            );
            self::runProcess(['php',$target.'/bin/migrate.php'],$output);
            $healthUrl=$job['target_environment']==='staging'?$config['staging_health_url']:$config['production_health_url'];
            self::assertHealth($healthUrl,$output);
            $pdo->prepare("UPDATE bdc_deployment_jobs SET status='success',completed_at=NOW(),output=:output WHERE id=:id")
                ->execute(['output'=>implode("\n",$output),'id'=>$job['id']]);
            if($job['target_environment']==='staging'){
                $pdo->prepare("UPDATE bdc_release_candidates SET status='passed',staging_tested_sha=commit_sha,staged_at=NOW(),passed_at=NOW() WHERE id=:id")
                    ->execute(['id'=>$job['release_id']]);
            }else{
                $pdo->prepare("UPDATE bdc_release_candidates SET status='production',production_at=NOW() WHERE id=:id")
                    ->execute(['id'=>$job['release_id']]);
            }
        }catch(\Throwable $e){
            $output[]='FAILED: '.$e->getMessage();
            if($job['target_environment']==='production'&&is_string($productionBackup)&&$config!==[]){
                try{
                    self::restoreProductionFiles($config,$productionBackup,$output);
                    self::assertHealth($config['production_health_url'],$output);
                    $output[]='Automatic Production file rollback completed. The pre-deployment database backup was retained.';
                }catch(\Throwable $rollbackError){
                    $output[]='ROLLBACK FAILED: '.$rollbackError->getMessage();
                }
            }
            $pdo->prepare("UPDATE bdc_deployment_jobs SET status='failed',completed_at=NOW(),output=:output WHERE id=:id")
                ->execute(['output'=>implode("\n",$output),'id'=>$job['id']]);
            $releaseStatus=$job['target_environment']==='production'?'approved':'failed';
            $pdo->prepare("UPDATE bdc_release_candidates SET status=:status WHERE id=:id")
                ->execute(['status'=>$releaseStatus,'id'=>$job['release_id']]);
            throw $e;
        }
    }

    public static function validateProduction(PDO $pdo,int $releaseId):array
    {
        $config=self::settings();
        self::assertEnabled($config);
        $release=self::release($pdo,$releaseId);
        if((string)$release['status']!=='production'){
            throw new RuntimeException('Only a release already deployed to Production can be validated.');
        }

        $installed=ReleaseManagerService::installedVersion($config['production_path']);
        if(!$installed)throw new RuntimeException('The installed Production release manifest could not be read.');
        if(!hash_equals((string)$release['commit_sha'],(string)($installed['commit_sha']??''))){
            throw new RuntimeException('Production validation failed because the installed commit does not match this release.');
        }
        if(!hash_equals((string)$release['version'],(string)($installed['version']??''))){
            throw new RuntimeException('Production validation failed because the installed version does not match this release.');
        }
        if(($installed['environment']??'')!=='production'){
            throw new RuntimeException('Production validation failed because the installed manifest has the wrong environment.');
        }

        $output=[];
        self::assertHealth($config['production_health_url'],$output);
        $validatedAt=date(DATE_ATOM);
        $marker='[PRODUCTION_VALIDATED] Production '.$release['version'].' at commit '.substr((string)$release['commit_sha'],0,12).' validated at '.$validatedAt.'.';
        $stmt=$pdo->prepare("UPDATE bdc_deployment_jobs
            SET output=CONCAT(COALESCE(output,''),IF(COALESCE(output,'')='','',CHAR(10)),:marker)
            WHERE release_id=:release_id AND target_environment='production' AND status='success'
            ORDER BY id DESC LIMIT 1");
        $stmt->execute(['marker'=>$marker,'release_id'=>$releaseId]);
        if($stmt->rowCount()!==1)throw new RuntimeException('The successful Production deployment record could not be found.');

        return [
            'version'=>(string)$release['version'],
            'commit_sha'=>(string)$release['commit_sha'],
            'validated_at'=>$validatedAt,
        ];
    }

    public static function rollbackProduction(PDO $pdo,int $deploymentJobId,int $userId):array
    {
        $config=self::settings();
        self::assertEnabled($config);
        $stmt=$pdo->prepare("SELECT j.*,r.version FROM bdc_deployment_jobs j
            JOIN bdc_release_candidates r ON r.id=j.release_id
            WHERE j.id=:id AND j.action='deploy_production' AND j.target_environment='production' AND j.status='success'");
        $stmt->execute(['id'=>$deploymentJobId]);
        $deployment=$stmt->fetch();
        if(!$deployment)throw new RuntimeException('Only a successful Production deployment can be rolled back.');

        $output=(string)($deployment['output']??'');
        if(!preg_match('/(?:\[PRODUCTION_BACKUP\]\s*|Production files backed up to\s+)([^\r\n]+)/',$output,$match)){
            throw new RuntimeException('The verified pre-deployment Production backup could not be identified.');
        }
        $backup=realpath(trim($match[1]));
        $backupRoot=realpath($config['backup_path']);
        if($backup===false||$backupRoot===false||!str_starts_with($backup.DIRECTORY_SEPARATOR,$backupRoot.DIRECTORY_SEPARATOR)){
            throw new RuntimeException('The Production backup path is missing or outside the configured backup directory.');
        }
        $previousManifestPath=$backup.'/files/storage/release.json';
        $previousManifest=is_file($previousManifestPath)
            ?json_decode((string)file_get_contents($previousManifestPath),true)
            :null;
        if(!is_array($previousManifest)||empty($previousManifest['version'])||empty($previousManifest['commit_sha'])
            ||($previousManifest['environment']??'')!=='production'
            ||!preg_match(self::SHA_PATTERN,(string)$previousManifest['commit_sha'])){
            throw new RuntimeException('The backup does not contain a valid previous Production release manifest.');
        }

        $insert=$pdo->prepare("INSERT INTO bdc_deployment_jobs(
            release_id,action,target_environment,commit_sha,requested_by,status,started_at,output
        ) VALUES(:release_id,'rollback_production','production',:sha,:uid,'running',NOW(),:output)");
        $insert->execute([
            'release_id'=>$deployment['release_id'],
            'sha'=>$deployment['commit_sha'],
            'uid'=>$userId,
            'output'=>'Rollback requested for Production deployment job #'.$deploymentJobId.'.',
        ]);
        $rollbackJobId=(int)$pdo->lastInsertId();
        $rollbackOutput=['Rollback requested for Production deployment job #'.$deploymentJobId.'.'];

        try{
            self::restoreProductionFiles($config,$backup,$rollbackOutput);
            if(!copy($previousManifestPath,rtrim($config['production_path'],'/').'/storage/release.json')){
                throw new RuntimeException('The previous Production release manifest could not be restored.');
            }
            self::assertHealth($config['production_health_url'],$rollbackOutput);
            $rollbackOutput[]='Production rolled back to '.$previousManifest['version'].' at commit '.substr((string)$previousManifest['commit_sha'],0,12).'.';
            $pdo->prepare("UPDATE bdc_deployment_jobs SET status='success',completed_at=NOW(),output=:output WHERE id=:id")
                ->execute(['output'=>implode("\n",$rollbackOutput),'id'=>$rollbackJobId]);
            $pdo->prepare("UPDATE bdc_release_candidates SET status='rolled_back' WHERE id=:id")
                ->execute(['id'=>$deployment['release_id']]);
            $pdo->prepare("UPDATE bdc_release_candidates SET status='production',production_at=NOW() WHERE commit_sha=:sha")
                ->execute(['sha'=>$previousManifest['commit_sha']]);
            return ['version'=>(string)$previousManifest['version'],'commit_sha'=>(string)$previousManifest['commit_sha']];
        }catch(\Throwable $e){
            $rollbackOutput[]='ROLLBACK FAILED: '.$e->getMessage();
            try{
                self::deployTree($config['repository_path'],(string)$deployment['commit_sha'],$config['production_path'],$rollbackOutput);
                self::writeReleaseManifest($config['production_path'],(string)$deployment['version'],(string)$deployment['commit_sha'],'production');
                self::runProcess(['php',$config['production_path'].'/bin/migrate.php'],$rollbackOutput);
                self::assertHealth($config['production_health_url'],$rollbackOutput);
                $rollbackOutput[]='The original Production release was restored after the rollback failed.';
            }catch(\Throwable $restoreError){
                $rollbackOutput[]='ORIGINAL RELEASE RESTORE FAILED: '.$restoreError->getMessage();
            }
            $pdo->prepare("UPDATE bdc_deployment_jobs SET status='failed',completed_at=NOW(),output=:output WHERE id=:id")
                ->execute(['output'=>implode("\n",$rollbackOutput),'id'=>$rollbackJobId]);
            throw $e;
        }
    }

    public static function recoverStaleJobs(PDO $pdo):int
    {
        $pdo->beginTransaction();
        try{
            $stale=$pdo->query("SELECT id,release_id,status,target_environment FROM bdc_deployment_jobs
                WHERE (status='queued' AND requested_at<DATE_SUB(NOW(),INTERVAL 10 MINUTE))
                   OR (status='running' AND COALESCE(started_at,requested_at)<DATE_SUB(NOW(),INTERVAL 60 MINUTE))
                FOR UPDATE")->fetchAll();
            if(!$stale){$pdo->commit();return 0;}
            $jobUpdate=$pdo->prepare("UPDATE bdc_deployment_jobs
                SET status='failed',completed_at=NOW(),output=CONCAT(COALESCE(output,''),IF(COALESCE(output,'')='','',CHAR(10)),'Automatically closed after the deployment stopped responding.')
                WHERE id=:id");
            $releaseUpdate=$pdo->prepare("UPDATE bdc_release_candidates SET status=:status
                WHERE id=:id AND status IN ('queued','testing')");
            foreach($stale as $job){
                $jobUpdate->execute(['id'=>$job['id']]);
                $releaseUpdate->execute([
                    'status'=>$job['target_environment']==='production'?'approved':'failed',
                    'id'=>$job['release_id'],
                ]);
            }
            $pdo->commit();
            return count($stale);
        }catch(\Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }
    }

    private static function release(PDO $pdo,int $id):array
    {
        $stmt=$pdo->prepare('SELECT * FROM bdc_release_candidates WHERE id=:id');
        $stmt->execute(['id'=>$id]);
        $release=$stmt->fetch();
        if(!$release)throw new RuntimeException('Release not found.');
        return $release;
    }

    private static function versionForCommit(string $repo,string $sha):string
    {
        try{
            $json=self::runGit($repo,['show',$sha.':VERSION.json']);
            $data=json_decode($json,true);
            if(is_array($data)&&isset($data['version']))return (string)$data['version'];
        }catch(\Throwable){}
        return 'dev-'.substr($sha,0,7);
    }

    private static function deployTree(string $repo,string $sha,string $target,array &$output):void
    {
        $tmp=sys_get_temp_dir().'/bdc-release-'.bin2hex(random_bytes(8));
        $tree=$tmp.'/tree';
        if(!mkdir($tree,0700,true))throw new RuntimeException('Cannot create deployment workspace.');
        try{
            self::runProcess(['git','-C',$repo,'archive','--format=tar','--output='.$tmp.'/release.tar',$sha],$output);
            self::runProcess(['tar','-xf',$tmp.'/release.tar','-C',$tree],$output);
            self::runProcess(['rsync','-a','--chmod=D755','--delete','--exclude=config/config.php','--exclude=config/config.local.php','--exclude=storage/','--exclude=uploads/','--exclude=public/results/',$tree.'/',$target.'/'],$output);
        }finally{
            self::deleteTree($tmp);
        }
    }

    private static function backupProduction(array $config,string $sha,array &$output):string
    {
        $backup=rtrim($config['backup_path'],'/').'/'.date('Ymd-His').'-'.substr($sha,0,12);
        if(!is_dir(dirname($backup))||(!is_dir($backup)&&!mkdir($backup,0700,true)))throw new RuntimeException('Cannot create Production backup.');
        self::runProcess(['rsync','-a','--exclude=storage/backups/',$config['production_path'].'/',$backup.'/files/'],$output);
        $runner=dirname(__DIR__,2).'/bin/deployment-backup.php';
        if(!is_file($runner))throw new RuntimeException('Production database backup runner is missing.');
        self::runProcess(['php',$runner,$config['production_path'],$backup],$output);
        $output[]='[PRODUCTION_BACKUP] '.$backup;
        $output[]='Production files backed up to '.$backup;
        return $backup;
    }

    private static function restoreProductionFiles(array $config,string $backup,array &$output):void
    {
        $files=$backup.'/files';
        if(!is_dir($files))throw new RuntimeException('Production rollback files are missing.');
        self::runProcess(['rsync','-a','--delete','--exclude=config/config.php','--exclude=config/config.local.php','--exclude=storage/','--exclude=uploads/','--exclude=public/results/',$files.'/',$config['production_path'].'/'],$output);
    }

    private static function assertHealth(string $url,array &$output):void
    {
        if($url==='')throw new RuntimeException('Health URL is not configured.');
        $body='';
        for($attempt=1;$attempt<=3;$attempt++){
            $body=trim((string)@file_get_contents($url));
            if(($json=json_decode($body,true))&&($json['status']??null)==='ok'){$output[]='Health check passed.';return;}
            sleep(1);
        }
        throw new RuntimeException('Health check failed: '.mb_substr($body,0,200));
    }

    private static function writeReleaseManifest(string $target,string $version,string $sha,string $environment):void
    {
        $storage=rtrim($target,'/').'/storage';
        if(!is_dir($storage)&&!mkdir($storage,0755,true))throw new RuntimeException('Cannot create release manifest directory.');
        $payload=json_encode([
            'version'=>$version,
            'commit_sha'=>$sha,
            'environment'=>$environment,
            'deployed_at'=>date(DATE_ATOM),
        ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
        if($payload===false||file_put_contents($storage.'/release.json',$payload."\n",LOCK_EX)===false){
            throw new RuntimeException('Cannot record the installed release version.');
        }
        @chmod($storage.'/release.json',0644);
    }

    private static function assertEnabled(array $config):void
    {
        if(ReleaseManagerService::environment()!=='staging'){
            throw new RuntimeException('Release Manager is available only from the Staging environment.');
        }
        if(!$config['enabled'])throw new RuntimeException('Deployment dashboard is not enabled in config/config.php.');
        foreach(['repository_path','staging_path','production_path','backup_path'] as $key){
            if($config[$key]==='')throw new RuntimeException('Missing deployment setting: '.$key);
        }
    }

    private static function assertSafeTarget(string $target):void
    {
        $target=rtrim($target,'/');
        if($target===''||$target==='/'||strlen($target)<20||!is_dir($target))throw new RuntimeException('Unsafe or missing deployment target.');
        if(!is_file($target.'/config/config.php'))throw new RuntimeException('Target configuration file is missing.');
    }

    private static function runGit(string $repo,array $args):string
    {
        $output=[];
        self::runProcess(array_merge(['git','-C',$repo],$args),$output);
        return implode("\n",$output);
    }

    private static function runProcess(array $command,array &$output):void
    {
        $escaped=implode(' ',array_map('escapeshellarg',$command));
        exec($escaped.' 2>&1',$lines,$code);
        foreach($lines as $line)$output[]=$line;
        if($code!==0)throw new RuntimeException('Command failed: '.implode(' ',array_slice($command,0,3)));
    }

    private static function deleteTree(string $path):void
    {
        if(!is_dir($path))return;
        $iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);
        foreach($iterator as $item){$item->isDir()?rmdir($item->getPathname()):unlink($item->getPathname());}
        rmdir($path);
    }
}
