<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class StagingResultsRepositorySyncService
{
    public static function status(): array
    {
        $path=self::statusPath();
        if(!is_file($path))return ['status'=>'never'];
        $data=json_decode((string)file_get_contents($path),true);
        return is_array($data)?$data:['status'=>'unknown'];
    }

    public static function sync(): array
    {
        if(ReleaseManagerService::environment()!=='staging'){
            throw new RuntimeException('Results Repository sync is available only from Staging.');
        }

        $deployment=DeploymentPipelineService::settings();
        $source=self::repositoryRoot((string)$deployment['production_path'],'production');
        $target=self::repositoryRoot((string)$deployment['staging_path'],'staging');
        self::assertSafeRepository($source,'Production');
        self::assertSafeRepository($target,'Staging');
        if(hash_equals($source,$target))throw new RuntimeException('Production and Staging repository paths must be different.');

        $backupRoot=rtrim((string)$deployment['backup_path'],'/').'/results-repository-sync';
        if(!is_dir($backupRoot)&&!mkdir($backupRoot,0700,true)&&!is_dir($backupRoot)){
            throw new RuntimeException('The Results Repository backup directory could not be created.');
        }
        $backup=$backupRoot.'/'.date('Ymd-His');
        if(!mkdir($backup,0700,true))throw new RuntimeException('The Staging repository backup could not be created.');

        $startedAt=date(DATE_ATOM);
        self::writeStatus(['status'=>'running','started_at'=>$startedAt]);
        try{
            self::runRsync($target.'/',$backup.'/');
            self::runRsync($source.'/',$target.'/',true);
            $sourceFiles=self::fileCount($source);
            $targetFiles=self::fileCount($target);
            if($sourceFiles!==$targetFiles||!self::repositoriesMatch($source,$target)){
                throw new RuntimeException('Repository verification failed because Staging does not exactly match Production.');
            }
            $result=[
                'status'=>'success',
                'started_at'=>$startedAt,
                'completed_at'=>date(DATE_ATOM),
                'files'=>$targetFiles,
                'backup_path'=>$backup,
            ];
            self::writeStatus($result);
            return $result;
        }catch(\Throwable $e){
            try{self::runRsync($backup.'/',$target.'/',true);}catch(\Throwable){}
            self::writeStatus([
                'status'=>'failed',
                'started_at'=>$startedAt,
                'completed_at'=>date(DATE_ATOM),
                'error'=>$e->getMessage(),
            ]);
            throw $e;
        }
    }

    private static function repositoryRoot(string $applicationPath,string $environment): string
    {
        $applicationPath=rtrim(str_replace('\\','/',$applicationPath),'/');
        $marker='/public_html/';
        $position=strpos($applicationPath,$marker);
        if($position===false)throw new RuntimeException('The '.$environment.' account path could not be derived safely.');
        $accountRoot=substr($applicationPath,0,$position);
        if($accountRoot===''||$accountRoot==='/')throw new RuntimeException('Unsafe '.$environment.' account path.');
        return $accountRoot.'/.bdc-results/'.$environment;
    }

    private static function assertSafeRepository(string $path,string $label): void
    {
        if($path===''||$path==='/'||strlen($path)<20||!str_ends_with($path,'/.bdc-results/'.strtolower($label))){
            throw new RuntimeException($label.' Results Repository path is unsafe.');
        }
        if(!is_dir($path)){
            if($label==='Production'||!mkdir($path,0750,true)||!is_dir($path)){
                throw new RuntimeException($label.' Results Repository is missing.');
            }
        }
        if($label==='Production'&&!is_readable($path))throw new RuntimeException('Production Results Repository is not readable.');
        if($label==='Staging'&&!is_writable($path))throw new RuntimeException('Staging Results Repository is not writable.');
    }

    private static function runRsync(string $source,string $target,bool $delete=false): void
    {
        $parts=['rsync','-a'];
        if($delete)$parts[]='--delete';
        $parts[]='--';
        $parts[]=$source;
        $parts[]=$target;
        $command=implode(' ',array_map('escapeshellarg',$parts));
        exec($command.' 2>&1',$output,$code);
        if($code!==0)throw new RuntimeException('Results Repository file synchronization failed.');
    }

    private static function fileCount(string $path): int
    {
        $count=0;
        $iterator=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path,\FilesystemIterator::SKIP_DOTS));
        foreach($iterator as $item){if($item->isFile())$count++;}
        return $count;
    }

    private static function repositoriesMatch(string $source,string $target): bool
    {
        $parts=['rsync','-ani','--checksum','--delete','--',$source.'/',$target.'/'];
        $command=implode(' ',array_map('escapeshellarg',$parts));
        exec($command.' 2>&1',$output,$code);
        return $code===0&&$output===[];
    }

    private static function statusPath(): string
    {
        return dirname(__DIR__,2).'/storage/results-repository-sync.json';
    }

    private static function writeStatus(array $status): void
    {
        $json=json_encode($status,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
        if($json!==false)file_put_contents(self::statusPath(),$json."\n",LOCK_EX);
    }
}
