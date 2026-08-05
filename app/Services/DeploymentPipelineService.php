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
        $stmt=$pdo->prepare("INSERT INTO bdc_deployment_jobs(release_id,action,target_environment,commit_sha,requested_by)
            VALUES(:release_id,:action,:target,:sha,:uid)");
        $stmt->execute(['release_id'=>$releaseId,'action'=>$action,'target'=>$target,'sha'=>$release['commit_sha'],'uid'=>$userId]);
        $pdo->prepare("UPDATE bdc_release_candidates SET status='queued' WHERE id=:id")->execute(['id'=>$releaseId]);
        return (int)$pdo->lastInsertId();
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
        $output=[];
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
            $pdo->prepare("UPDATE bdc_release_candidates SET status='failed' WHERE id=:id")
                ->execute(['id'=>$job['release_id']]);
            throw $e;
        }
    }

    public static function recoverStaleJobs(PDO $pdo):int
    {
        $pdo->beginTransaction();
        try{
            $stale=$pdo->query("SELECT id,release_id,status FROM bdc_deployment_jobs
                WHERE (status='queued' AND requested_at<DATE_SUB(NOW(),INTERVAL 10 MINUTE))
                   OR (status='running' AND COALESCE(started_at,requested_at)<DATE_SUB(NOW(),INTERVAL 60 MINUTE))
                FOR UPDATE")->fetchAll();
            if(!$stale){$pdo->commit();return 0;}
            $jobUpdate=$pdo->prepare("UPDATE bdc_deployment_jobs
                SET status='failed',completed_at=NOW(),output=CONCAT(COALESCE(output,''),IF(COALESCE(output,'')='','',CHAR(10)),'Automatically closed after the deployment stopped responding.')
                WHERE id=:id");
            $releaseUpdate=$pdo->prepare("UPDATE bdc_release_candidates SET status='failed'
                WHERE id=:id AND status IN ('queued','testing')");
            foreach($stale as $job){
                $jobUpdate->execute(['id'=>$job['id']]);
                $releaseUpdate->execute(['id'=>$job['release_id']]);
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
        $before=count($output);
        $bootstrap=$config['production_path'].'/bootstrap.php';
        $code='require '.var_export($bootstrap,true).';$r=(new App\\Services\\BackupService())->createDatabaseBackup();echo $r["name"].PHP_EOL;';
        self::runProcess(['php','-r',$code],$output);
        $databaseName=trim((string)($output[count($output)-1]??''));
        if(!preg_match('/^BDC_DB_[A-Za-z0-9_.-]+\.sql\.gz$/',$databaseName))throw new RuntimeException('Production database backup did not return a valid file.');
        $databaseSource=$config['production_path'].'/storage/backups/database/'.$databaseName;
        if(!is_file($databaseSource)||!copy($databaseSource,$backup.'/'.$databaseName))throw new RuntimeException('Production database backup could not be retained with the release backup.');
        $output=array_merge(array_slice($output,0,$before),['Production database backed up: '.$backup.'/'.$databaseName]);
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
