<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

final class BackupAutomationService
{
    private string $root;
    private PDO $pdo;
    private BackupService $backupService;

    public function __construct(?string $root=null)
    {
        $this->root=$root??dirname(__DIR__,2);
        $this->pdo=Database::connection();
        $this->backupService=new BackupService($this->root);
    }

    public function settings():array
    {
        $row=$this->pdo->query("SELECT * FROM bdc_backup_settings WHERE id=1")->fetch();
        return $row?:[
            'enabled'=>0,'frequency'=>'daily','backup_time'=>'03:00:00','weekday'=>1,'month_day'=>1,
            'backup_type'=>'full','keep_count'=>7,'server_keep_count'=>7,'drive_keep_count'=>30,'google_drive_enabled'=>0,'google_drive_folder_id'=>'',
            'service_account_path'=>'storage/private/google-drive-service-account.json','last_run_at'=>null,
            'next_run_at'=>null,
        ];
    }

    public function saveSettings(array $data,?array $serviceAccountUpload=null):array
    {
        $frequency=in_array($data['frequency']??'daily',['daily','weekly','monthly'],true)?$data['frequency']:'daily';
        $backupType=in_array($data['backup_type']??'full',['database','site','full'],true)?$data['backup_type']:'full';
        $time=preg_match('/^\d{2}:\d{2}$/',(string)($data['backup_time']??''))?(string)$data['backup_time'].':00':'03:00:00';
        $weekday=max(1,min(7,(int)($data['weekday']??1)));
        $monthDay=max(1,min(28,(int)($data['month_day']??1)));
        $serverKeep=max(1,min(100,(int)($data['server_keep_count']??$data['keep_count']??7)));
        $driveKeep=max(1,min(365,(int)($data['drive_keep_count']??30)));
        $path=(string)($this->settings()['service_account_path']??'storage/private/google-drive-service-account.json');

        if($serviceAccountUpload && ($serviceAccountUpload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
            if(($serviceAccountUpload['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK)throw new RuntimeException('Service-account JSON upload failed.');
            if((int)($serviceAccountUpload['size']??0)>1024*1024)throw new RuntimeException('Service-account JSON must be 1 MB or smaller.');
            $json=json_decode((string)file_get_contents((string)$serviceAccountUpload['tmp_name']),true);
            if(!is_array($json)||empty($json['client_email'])||empty($json['private_key']))throw new RuntimeException('Invalid Google service-account JSON.');
            $dir=$this->root.'/storage/private';
            if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('Could not create secure credential folder.');
            $absolute=$dir.'/google-drive-service-account.json';
            if(!move_uploaded_file((string)$serviceAccountUpload['tmp_name'],$absolute))throw new RuntimeException('Could not store Google credentials.');
            @chmod($absolute,0600);
            $path='storage/private/google-drive-service-account.json';
        }

        $next=$this->calculateNextRun($frequency,$time,$weekday,$monthDay);
        $stmt=$this->pdo->prepare("
            INSERT INTO bdc_backup_settings(
             id,enabled,frequency,backup_time,weekday,month_day,backup_type,keep_count,server_keep_count,drive_keep_count,
             google_drive_enabled,google_drive_folder_id,service_account_path,next_run_at,updated_at
            ) VALUES(
             1,:enabled,:frequency,:backup_time,:weekday,:month_day,:backup_type,:keep_count,:server_keep,:drive_keep,
             :drive_enabled,:folder_id,:credential_path,:next_run,NOW()
            )
            ON DUPLICATE KEY UPDATE
             enabled=VALUES(enabled),frequency=VALUES(frequency),backup_time=VALUES(backup_time),
             weekday=VALUES(weekday),month_day=VALUES(month_day),backup_type=VALUES(backup_type),
             keep_count=VALUES(keep_count),server_keep_count=VALUES(server_keep_count),drive_keep_count=VALUES(drive_keep_count),google_drive_enabled=VALUES(google_drive_enabled),
             google_drive_folder_id=VALUES(google_drive_folder_id),
             service_account_path=VALUES(service_account_path),next_run_at=VALUES(next_run_at),updated_at=NOW()
        ");
        $stmt->execute([
            'enabled'=>!empty($data['enabled'])?1:0,
            'frequency'=>$frequency,'backup_time'=>$time,'weekday'=>$weekday,'month_day'=>$monthDay,
            'backup_type'=>$backupType,'keep_count'=>$serverKeep,'server_keep'=>$serverKeep,'drive_keep'=>$driveKeep,
            'drive_enabled'=>!empty($data['google_drive_enabled'])?1:0,
            'folder_id'=>GoogleDriveBackupService::normaliseFolderId((string)($data['google_drive_folder_id']??'')),
            'credential_path'=>$path,'next_run'=>$next,
        ]);
        return $this->settings();
    }

    private function calculateNextRun(string $frequency,string $time,int $weekday,int $monthDay):string
    {
        $now=new \DateTimeImmutable('now');
        [$hour,$minute]=array_map('intval',explode(':',$time));
        $candidate=$now->setTime($hour,$minute);
        if($frequency==='daily'){
            if($candidate<=$now)$candidate=$candidate->modify('+1 day');
        }elseif($frequency==='weekly'){
            $candidate=$candidate->modify('monday this week')->modify('+'.($weekday-1).' days');
            if($candidate<=$now)$candidate=$candidate->modify('+1 week');
        }else{
            $candidate=$candidate->setDate((int)$now->format('Y'),(int)$now->format('m'),$monthDay);
            if($candidate<=$now)$candidate=$candidate->modify('first day of next month')->setDate((int)$candidate->format('Y'),(int)$candidate->format('m'),$monthDay);
        }
        return $candidate->format('Y-m-d H:i:s');
    }

    public function due():bool
    {
        $s=$this->settings();
        return !empty($s['enabled']) && !empty($s['next_run_at']) && strtotime((string)$s['next_run_at'])<=time();
    }

    public function run(bool $force=false,?int $userId=null):array
    {
        $settings=$this->settings();
        if(!$force && !$this->due())return ['skipped'=>true,'message'=>'Backup is not due yet.'];
        $started=date('Y-m-d H:i:s');
        $runId=0;
        $this->pdo->prepare("INSERT INTO bdc_backup_runs(backup_type,status,started_at,triggered_by) VALUES(:type,'running',NOW(),:user_id)")
            ->execute(['type'=>$settings['backup_type'],'user_id'=>$userId]);
        $runId=(int)$this->pdo->lastInsertId();

        try{
            $result=match($settings['backup_type']){
                'database'=>$this->backupService->createDatabaseBackup($userId),
                'site'=>$this->backupService->createSiteBackup($userId),
                default=>$this->backupService->createFullBackup($userId),
            };
            $path=$this->backupService->resolve($result['type'],$result['name']);
            $driveStatus='disabled';$driveId=null;$driveLink=null;$driveError=null;

            if(!empty($settings['google_drive_enabled'])){
                try{
                    $drive=$this->drive($settings);
                    $uploaded=$drive->upload($path,$result['name']);
                    $driveStatus='uploaded';
                    $driveId=(string)$uploaded['id'];
                    $driveLink=(string)($uploaded['webViewLink']??'');
                }catch(\Throwable $e){
                    $driveStatus='failed';$driveError=$e->getMessage();
                }
            }

            $this->pdo->prepare("
              UPDATE bdc_backup_runs SET status='success',file_name=:name,local_path=:path,file_size=:size,
              checksum=:checksum,google_drive_status=:drive_status,google_drive_file_id=:drive_id,
              google_drive_link=:drive_link,error_message=:error,completed_at=NOW() WHERE id=:id
            ")->execute([
                'name'=>$result['name'],'path'=>'storage/backups/'.$result['type'].'/'.$result['name'],
                'size'=>$result['size'],'checksum'=>$result['checksum'],'drive_status'=>$driveStatus,
                'drive_id'=>$driveId,'drive_link'=>$driveLink,'error'=>$driveError,'id'=>$runId,
            ]);

            $next=$this->calculateNextRun(
                (string)$settings['frequency'],(string)$settings['backup_time'],
                (int)$settings['weekday'],(int)$settings['month_day']
            );
            $this->pdo->prepare("UPDATE bdc_backup_settings SET last_run_at=NOW(),next_run_at=:next_run WHERE id=1")
                ->execute(['next_run'=>$next]);

            $this->applyRetention((int)($settings['server_keep_count']??$settings['keep_count']),(int)($settings['drive_keep_count']??30),$settings);

            return ['run_id'=>$runId,'backup'=>$result,'google_drive_status'=>$driveStatus,'google_drive_error'=>$driveError];
        }catch(\Throwable $e){
            $this->pdo->prepare("UPDATE bdc_backup_runs SET status='failed',error_message=:error,completed_at=NOW() WHERE id=:id")
                ->execute(['error'=>$e->getMessage(),'id'=>$runId]);
            throw $e;
        }
    }

    public function testGoogleDrive():array
    {
        return $this->drive($this->settings())->testConnection();
    }

    private function drive(array $settings):GoogleDriveBackupService
    {
        $path=$this->root.'/'.ltrim((string)$settings['service_account_path'],'/');
        return new GoogleDriveBackupService($path,(string)$settings['google_drive_folder_id']);
    }

    public function applyRetention(int $serverKeep,int $driveKeep,array $settings):int
    {
        $serverKeep=max(1,min(100,$serverKeep));$driveKeep=max(1,min(365,$driveKeep));
        $runs=$this->pdo->query("SELECT * FROM bdc_backup_runs WHERE status='success' ORDER BY completed_at DESC,id DESC")->fetchAll();
        $deleted=0;
        foreach(array_slice($runs,$driveKeep) as $run){
            if(!empty($run['google_drive_file_id']) && !empty($settings['google_drive_enabled'])){
                try{$this->drive($settings)->delete((string)$run['google_drive_file_id']);$this->pdo->prepare("UPDATE bdc_backup_runs SET google_drive_status='disabled',google_drive_file_id=NULL,google_drive_link=NULL WHERE id=:id")->execute(['id'=>$run['id']]);}catch(\Throwable $e){}
            }
        }
        $deleted=$this->backupService->cleanup($serverKeep);
        return $deleted;
    }

    public function history(int $limit=100):array
    {
        $stmt=$this->pdo->prepare("SELECT * FROM bdc_backup_runs ORDER BY id DESC LIMIT :limit");
        $stmt->bindValue('limit',max(1,min(500,$limit)),PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
