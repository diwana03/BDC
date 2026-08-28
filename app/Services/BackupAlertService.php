<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use PDO;

final class BackupAlertService
{
    public function __construct(private PDO $pdo)
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS bdc_backup_alerts(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            alert_type ENUM('backup_failed','drive_upload_failed') NOT NULL,
            title VARCHAR(190) NOT NULL,
            message TEXT NOT NULL,
            status ENUM('open','acknowledged','resolved') NOT NULL DEFAULT 'open',
            occurrence_count INT UNSIGNED NOT NULL DEFAULT 1,
            first_occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            email_sent_at DATETIME NULL,
            acknowledged_by BIGINT UNSIGNED NULL,
            acknowledged_at DATETIME NULL,
            resolved_at DATETIME NULL,
            INDEX idx_backup_alert_status(status,last_occurred_at),
            INDEX idx_backup_alert_type(alert_type,status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function failure(string $type,string $message,?int $runId=null):void
    {
        if(!in_array($type,['backup_failed','drive_upload_failed'],true))return;
        $title=$type==='backup_failed'?'BDC backup failed':'BDC Google Drive upload failed';
        $message=trim($message)?:'Unknown backup error.';
        $find=$this->pdo->prepare("SELECT id,email_sent_at FROM bdc_backup_alerts WHERE alert_type=:type AND status IN ('open','acknowledged') ORDER BY id DESC LIMIT 1");
        $find->execute(['type'=>$type]);$existing=$find->fetch();$id=(int)($existing['id']??0);
        if($id>0){
            $this->pdo->prepare("UPDATE bdc_backup_alerts SET title=:title,message=:message,status='open',occurrence_count=occurrence_count+1,last_occurred_at=NOW(),acknowledged_by=NULL,acknowledged_at=NULL WHERE id=:id")
                ->execute(['title'=>$title,'message'=>$message,'id'=>$id]);
        }else{
            $this->pdo->prepare("INSERT INTO bdc_backup_alerts(alert_type,title,message) VALUES(:type,:title,:message)")
                ->execute(['type'=>$type,'title'=>$title,'message'=>$message]);
            $id=(int)$this->pdo->lastInsertId();
        }
        // Avoid flooding every cron interval while a provider remains down.
        $lastEmail=(string)($existing['email_sent_at']??'');
        $shouldEmail=$lastEmail==='' || time()-(strtotime($lastEmail)?:0)>=21600;
        if($shouldEmail){
            $this->email($title,$message,$runId,false);
            $this->pdo->prepare('UPDATE bdc_backup_alerts SET email_sent_at=NOW() WHERE id=:id')->execute(['id'=>$id]);
        }
    }

    public function recovered(string $type,?int $runId=null):void
    {
        $find=$this->pdo->prepare("SELECT id,title FROM bdc_backup_alerts WHERE alert_type=:type AND status IN ('open','acknowledged') ORDER BY id DESC LIMIT 1");
        $find->execute(['type'=>$type]);$alert=$find->fetch();if(!$alert)return;
        $this->pdo->prepare("UPDATE bdc_backup_alerts SET status='resolved',resolved_at=NOW() WHERE id=:id")->execute(['id'=>$alert['id']]);
        $label=$type==='backup_failed'?'BDC backup':'BDC Google Drive upload';
        $this->email($label.' recovered','The next scheduled operation completed successfully. The previous alert is now resolved.',$runId,true);
    }

    public function active():array
    {
        return $this->pdo->query("SELECT * FROM bdc_backup_alerts WHERE status IN ('open','acknowledged') ORDER BY FIELD(status,'open','acknowledged'),last_occurred_at DESC")->fetchAll();
    }

    public function acknowledge(int $id,int $userId):void
    {
        $stmt=$this->pdo->prepare("UPDATE bdc_backup_alerts SET status='acknowledged',acknowledged_by=:user,acknowledged_at=NOW() WHERE id=:id AND status='open'");
        $stmt->execute(['user'=>$userId?:null,'id'=>$id]);
    }

    private function email(string $subject,string $message,?int $runId,bool $recovery):bool
    {
        $recipients=$this->pdo->query("SELECT email FROM bdc_users WHERE role='super_admin' AND status='active' AND email<>''")->fetchAll(PDO::FETCH_COLUMN);
        if(!$recipients)return false;
        $environment=(string)Config::get('app.environment','production');
        $body="Bachata Dance Council backup alert\n\n".$subject."\n".$message."\n\nEnvironment: ".$environment;
        if($runId)$body.="\nBackup run: #".$runId;
        $body.="\nTime: ".date(DATE_ATOM)."\n\nOpen the BDC Admin Dashboard for details.";
        $headers="From: no-reply@bachatadancecouncil.com\r\nContent-Type: text/plain; charset=UTF-8";
        $sent=false;foreach(array_unique(array_map('strval',$recipients)) as $email){if(@mail($email,'['.strtoupper($environment).'] '.($recovery?'RECOVERED: ':'ALERT: ').$subject,$body,$headers))$sent=true;}
        return $sent;
    }
}
