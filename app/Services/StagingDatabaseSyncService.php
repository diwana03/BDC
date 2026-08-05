<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use RuntimeException;

final class StagingDatabaseSyncService
{
    private const PRESERVED_TABLES=['bdc_users','bdc_user_permissions','bdc_release_candidates','bdc_deployment_jobs','bdc_releases','bdc_release_installations','bdc_schema_migrations'];

    public static function settings():array
    {
        $sync=(array)Config::get('staging_database_sync',[]);$schedule=(string)($sync['schedule']??'off');$scheduleFile=self::schedulePath();
        if(is_file($scheduleFile)){$saved=trim((string)file_get_contents($scheduleFile));if(in_array($saved,['off','daily','weekly'],true))$schedule=$saved;}
        return ['enabled'=>(bool)($sync['enabled']??false),'schedule'=>$schedule,'quiet_hour'=>(int)($sync['quiet_hour']??3),'source'=>(array)($sync['production_readonly_database']??[])];
    }

    public static function saveSchedule(string $schedule):void
    {
        self::assertStagingOnly();if(!in_array($schedule,['off','daily','weekly'],true))throw new RuntimeException('Choose Off, Daily or Weekly.');
        if(file_put_contents(self::schedulePath(),$schedule."\n",LOCK_EX)===false)throw new RuntimeException('Could not save database sync schedule.');
    }

    public static function status():array
    {
        $file=self::statePath();if(!is_file($file))return ['status'=>'never','last_success'=>null,'message'=>'No database sync has run.'];
        $data=json_decode((string)file_get_contents($file),true);return is_array($data)?$data:['status'=>'unknown','last_success'=>null,'message'=>'Sync status is unreadable.'];
    }

    public static function isDue():bool
    {
        $settings=self::settings();$schedule=$settings['schedule'];if(!in_array($schedule,['daily','weekly'],true)||date('G')!==str_pad((string)$settings['quiet_hour'],1,'0'))return false;
        $last=(string)(self::status()['last_success']??'');if($last==='')return true;$age=time()-(strtotime($last)?:0);return $age>=($schedule==='daily'?82800:601200);
    }

    public static function sync(int $userId=0):array
    {
        self::assertStagingOnly();$settings=self::settings();if(!$settings['enabled'])throw new RuntimeException('Production to Staging database sync is disabled in Staging configuration.');
        $control=\App\Core\Database::connection();
        if((int)$control->query("SELECT COUNT(*) FROM bdc_deployment_jobs WHERE status IN('queued','running')")->fetchColumn()>0)throw new RuntimeException('Database sync is blocked while a deployment is running.');
        if((int)$control->query("SELECT COUNT(*) FROM bdc_scoring_rounds WHERE status NOT IN('completed','archived','published','discarded') AND updated_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)")->fetchColumn()>0)throw new RuntimeException('Database sync is blocked because a scoring round was active during the last 15 minutes.');
        $source=$settings['source'];$target=(array)Config::get('database',[]);self::validateDatabase($source,'Production read-only source');self::validateDatabase($target,'Staging target');
        if((string)$source['name']===(string)$target['name']&&(string)$source['host']===(string)$target['host'])throw new RuntimeException('Sync aborted: Production source and Staging target resolve to the same database.');
        $backupRoot=rtrim((string)Config::get('deployment.backup_path',dirname(__DIR__,2).'/storage/backups'),'/').'/staging-db-sync';
        if(!is_dir($backupRoot)&&!mkdir($backupRoot,0700,true))throw new RuntimeException('Cannot create the Staging database sync backup directory.');
        $lock=fopen($backupRoot.'/sync.lock','c+');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Another database sync is already running.');
        $stamp=date('Ymd_His');$stagingBackup=$backupRoot.'/staging-before-'.$stamp.'.sql.gz';$productionDump=$backupRoot.'/production-'.$stamp.'.sql.gz';
        self::writeState(['status'=>'running','started_at'=>date(DATE_ATOM),'requested_by'=>$userId,'message'=>'Backup']);
        try{
            self::dump($target,$stagingBackup,[]);
            self::writeState(['status'=>'running','started_at'=>date(DATE_ATOM),'requested_by'=>$userId,'message'=>'Export']);
            $ignored=array_map(fn($table)=>(string)$source['name'].'.'.$table,self::PRESERVED_TABLES);self::dump($source,$productionDump,$ignored);
            self::writeState(['status'=>'running','started_at'=>date(DATE_ATOM),'requested_by'=>$userId,'message'=>'Import']);self::import($target,$productionDump);
            self::writeState(['status'=>'running','started_at'=>date(DATE_ATOM),'requested_by'=>$userId,'message'=>'Migrate']);SchemaUpdater::run(\App\Core\Database::connection());
            $pdo=\App\Core\Database::connection();$pdo->query('SELECT 1')->fetchColumn();
            $result=['status'=>'success','last_success'=>date(DATE_ATOM),'requested_by'=>$userId,'backup'=>$stagingBackup,'message'=>'Production data synced one-way to Staging.'];self::writeState($result);return $result;
        }catch(\Throwable $e){
            try{if(is_file($stagingBackup))self::import($target,$stagingBackup);}catch(\Throwable $restore){self::writeState(['status'=>'failed','failed_at'=>date(DATE_ATOM),'message'=>$e->getMessage().' Automatic restore also failed: '.$restore->getMessage()]);throw new RuntimeException('Sync failed and Staging restore also failed. Check the server log immediately.');}
            self::writeState(['status'=>'failed','failed_at'=>date(DATE_ATOM),'message'=>$e->getMessage().' Existing Staging database restored.']);throw new RuntimeException('Database sync failed. The previous Staging database was restored.');
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }

    private static function assertStagingOnly():void
    {
        if(ReleaseManagerService::environment()!=='staging')throw new RuntimeException('Database sync is physically restricted to the Staging runtime.');
    }
    private static function validateDatabase(array $db,string $label):void
    {
        foreach(['host','name','user','password'] as $key)if(trim((string)($db[$key]??''))==='')throw new RuntimeException($label.' setting is missing: '.$key);
        if(!preg_match('/^[A-Za-z0-9_]+$/',(string)$db['name']))throw new RuntimeException($label.' database name is invalid.');
    }
    private static function dump(array $db,string $file,array $ignored):void
    {
        $args=['mysqldump','--single-transaction','--quick','--skip-lock-tables','--skip-triggers','--host='.(string)$db['host'],'--port='.(string)($db['port']??3306),'--user='.(string)$db['user'],'--default-character-set='.(string)($db['charset']??'utf8mb4')];foreach($ignored as $table)$args[]='--ignore-table='.$table;$args[]=(string)$db['name'];self::pipe($args,['gzip','-c'],$file,(string)$db['password']);
    }
    private static function import(array $db,string $file):void
    {
        self::pipe(['gzip','-dc',$file],['mysql','--host='.(string)$db['host'],'--port='.(string)($db['port']??3306),'--user='.(string)$db['user'],'--default-character-set='.(string)($db['charset']??'utf8mb4'),(string)$db['name']],null,(string)$db['password']);
    }
    private static function pipe(array $first,array $second,?string $outputFile,string $password):void
    {
        $cmd=implode(' ',array_map('escapeshellarg',$first)).' | '.implode(' ',array_map('escapeshellarg',$second));if($outputFile!==null)$cmd.=' > '.escapeshellarg($outputFile);
        $env=$_ENV;$env['MYSQL_PWD']=$password;$proc=proc_open(['/bin/sh','-c',$cmd],[0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['pipe','w']],$pipes,null,$env);if(!is_resource($proc))throw new RuntimeException('Could not start database sync command.');$error=stream_get_contents($pipes[2]);fclose($pipes[2]);$code=proc_close($proc);if($code!==0)throw new RuntimeException('Database command failed: '.mb_substr(trim((string)$error),0,300));
    }
    private static function statePath():string{return dirname(__DIR__,2).'/storage/staging-db-sync-status.json';}
    private static function schedulePath():string{return dirname(__DIR__,2).'/storage/staging-db-sync-schedule.txt';}
    private static function writeState(array $state):void{$json=json_encode($state,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);if($json===false||file_put_contents(self::statePath(),$json."\n",LOCK_EX)===false)throw new RuntimeException('Cannot write database sync status.');}
}
