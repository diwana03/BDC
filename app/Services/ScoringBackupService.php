<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class ScoringBackupService
{
    public static function ensure(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_scoring_backups(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            round_id BIGINT UNSIGNED NOT NULL,
            data_mode ENUM('live','test') NOT NULL,
            backup_type ENUM('automatic','manual','pre_restore') NOT NULL DEFAULT 'automatic',
            action_name VARCHAR(100) NOT NULL,
            label VARCHAR(190) NULL,
            snapshot_hash CHAR(64) NOT NULL,
            snapshot_json LONGTEXT NOT NULL,
            summary_json LONGTEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            restored_by BIGINT UNSIGNED NULL,
            restored_at DATETIME NULL,
            restore_reason VARCHAR(500) NULL,
            is_protected TINYINT(1) NOT NULL DEFAULT 0,
            INDEX idx_scoring_backup_round(round_id,data_mode,created_at),
            INDEX idx_scoring_backup_hash(snapshot_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $column=$pdo->query("SHOW COLUMNS FROM bdc_scoring_backups LIKE 'is_protected'")->fetch();
        if(!$column)$pdo->exec("ALTER TABLE bdc_scoring_backups ADD COLUMN is_protected TINYINT(1) NOT NULL DEFAULT 0 AFTER restore_reason");
    }

    public static function create(PDO $pdo,int $roundId,bool $test,int $userId,string $type,string $action,string $label=''):int
    {
        self::ensure($pdo);
        $roundTable=$test?'bdc_test_scoring_rounds':'bdc_scoring_rounds';
        $eventTable=$test?'bdc_test_events':'bdc_events';
        $roundStmt=$pdo->prepare("SELECT e.name event_name FROM `{$roundTable}` r JOIN `{$eventTable}` e ON e.id=r.event_id WHERE r.id=:round");
        $roundStmt->execute(['round'=>$roundId]);
        $eventName=(string)$roundStmt->fetchColumn();
        if($eventName==='')throw new RuntimeException('Scoring round not found for backup.');
        if(!in_array($type,['automatic','manual','pre_restore'],true))$type='automatic';
        if(trim($label)==='')$label=$eventName.' · '.($type==='manual'?'Manual checkpoint':ucwords(str_replace('_',' ',$action))).' · '.date('H:i:s');
        $tables=self::tables($test);
        $snapshot=[];$summary=[];
        foreach($tables as $key=>$table){
            $stmt=$pdo->prepare("SELECT * FROM `{$table}` WHERE round_id=:round ORDER BY id");
            $stmt->execute(['round'=>$roundId]);
            $snapshot[$key]=$stmt->fetchAll();
            $summary[$key]=count($snapshot[$key]);
        }
        $json=json_encode(['schema'=>1,'round_id'=>$roundId,'data_mode'=>$test?'test':'live','tables'=>$snapshot],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $hash=hash('sha256',$json);
        $protected=$type==='manual'||$action==='archive_snapshot';
        $stmt=$pdo->prepare("INSERT INTO bdc_scoring_backups(round_id,data_mode,backup_type,action_name,label,snapshot_hash,snapshot_json,summary_json,created_by,is_protected) VALUES(:round,:mode,:type,:action,NULLIF(:label,''),:hash,:snapshot,:summary,:user,:protected)");
        $stmt->execute(['round'=>$roundId,'mode'=>$test?'test':'live','type'=>$type,'action'=>substr($action,0,100),'label'=>substr(trim($label),0,190),'hash'=>$hash,'snapshot'=>$json,'summary'=>json_encode($summary,JSON_UNESCAPED_SLASHES),'user'=>$userId?:null,'protected'=>$protected?1:0]);
        $id=(int)$pdo->lastInsertId();
        if($type==='automatic')self::trimAutomatic($pdo,$roundId,$test,25);
        return $id;
    }

    public static function restore(PDO $pdo,int $backupId,int $roundId,bool $test,int $userId,string $reason):array
    {
        self::ensure($pdo);$reason=trim($reason);
        if($reason==='')throw new RuntimeException('Enter the reason for restoring this scoring backup.');
        $stmt=$pdo->prepare("SELECT * FROM bdc_scoring_backups WHERE id=:id AND round_id=:round AND data_mode=:mode");
        $stmt->execute(['id'=>$backupId,'round'=>$roundId,'mode'=>$test?'test':'live']);$backup=$stmt->fetch();
        if(!$backup)throw new RuntimeException('Scoring backup not found for this round.');
        if(!hash_equals((string)$backup['snapshot_hash'],hash('sha256',(string)$backup['snapshot_json'])))throw new RuntimeException('Backup integrity check failed. Recovery was stopped.');
        $payload=json_decode((string)$backup['snapshot_json'],true,512,JSON_THROW_ON_ERROR);
        if((int)($payload['round_id']??0)!==$roundId || (string)($payload['data_mode']??'')!==($test?'test':'live'))throw new RuntimeException('Backup scope does not match this scoring round.');
        self::create($pdo,$roundId,$test,$userId,'pre_restore','restore_backup','Automatic safety copy before restoring backup #'.$backupId);
        $rows=(array)($payload['tables']??[]);$tables=self::tables($test);
        $deleteOrder=['final_results','final_marks','final_pairs','results','marks','sessions'];
        $insertOrder=['marks','results','final_pairs','final_marks','final_results','sessions'];
        $pdo->beginTransaction();
        try{
            foreach($deleteOrder as $key)$pdo->prepare("DELETE FROM `{$tables[$key]}` WHERE round_id=:round")->execute(['round'=>$roundId]);
            foreach($insertOrder as $key)self::insertRows($pdo,$tables[$key],(array)($rows[$key]??[]),$roundId);
            $pdo->prepare("UPDATE bdc_scoring_backups SET restored_by=:user,restored_at=NOW(),restore_reason=:reason WHERE id=:id")->execute(['user'=>$userId?:null,'reason'=>substr($reason,0,500),'id'=>$backupId]);
            $audit=$test?'bdc_test_scoring_audit':'bdc_scoring_audit';
            $pdo->prepare("INSERT INTO {$audit}(round_id,user_id,action,details_json) VALUES(:round,:user,'scoring_backup_restored',:details)")->execute(['round'=>$roundId,'user'=>$userId?:null,'details'=>json_encode(['backup_id'=>$backupId,'reason'=>$reason,'snapshot_hash'=>$backup['snapshot_hash']],JSON_UNESCAPED_SLASHES)]);
            $pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        return ['id'=>$backupId,'summary'=>json_decode((string)$backup['summary_json'],true)?:[]];
    }

    public static function list(PDO $pdo,int $roundId,bool $test,int $limit=25):array
    {
        self::ensure($pdo);$limit=max(1,min(100,$limit));
        $stmt=$pdo->prepare("SELECT id,backup_type,action_name,label,snapshot_hash,summary_json,created_by,created_at,restored_by,restored_at,restore_reason,is_protected FROM bdc_scoring_backups WHERE round_id=:round AND data_mode=:mode ORDER BY id DESC LIMIT {$limit}");
        $stmt->execute(['round'=>$roundId,'mode'=>$test?'test':'live']);return $stmt->fetchAll();
    }

    public static function delete(PDO $pdo,int $backupId,int $roundId,bool $test,int $userId,string $reason):array
    {
        self::ensure($pdo);$reason=trim($reason);
        if($reason==='')throw new RuntimeException('Enter the reason for deleting this scoring backup.');
        $mode=$test?'test':'live';
        $stmt=$pdo->prepare("SELECT id,backup_type,action_name,label,snapshot_hash,is_protected FROM bdc_scoring_backups WHERE id=:id AND round_id=:round AND data_mode=:mode");
        $stmt->execute(['id'=>$backupId,'round'=>$roundId,'mode'=>$mode]);$backup=$stmt->fetch();
        if(!$backup)throw new RuntimeException('Scoring backup not found for this round.');
        $audit=$test?'bdc_test_scoring_audit':'bdc_scoring_audit';
        $pdo->beginTransaction();
        try{
            $deleted=$pdo->prepare("DELETE FROM bdc_scoring_backups WHERE id=:id AND round_id=:round AND data_mode=:mode");
            $deleted->execute(['id'=>$backupId,'round'=>$roundId,'mode'=>$mode]);
            if($deleted->rowCount()!==1)throw new RuntimeException('Scoring backup could not be deleted.');
            $pdo->prepare("INSERT INTO {$audit}(round_id,user_id,action,details_json) VALUES(:round,:user,'scoring_backup_deleted',:details)")->execute([
                'round'=>$roundId,
                'user'=>$userId?:null,
                'details'=>json_encode(['backup_id'=>$backupId,'reason'=>substr($reason,0,500),'backup_type'=>$backup['backup_type'],'action_name'=>$backup['action_name'],'label'=>$backup['label'],'snapshot_hash'=>$backup['snapshot_hash'],'was_protected'=>(bool)$backup['is_protected']],JSON_UNESCAPED_SLASHES),
            ]);
            $pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        return ['id'=>$backupId,'was_protected'=>(bool)$backup['is_protected']];
    }

    public static function consolidateEventForArchive(PDO $pdo,int $eventId,bool $test,int $userId):array
    {
        self::ensure($pdo);
        $roundTable=$test?'bdc_test_scoring_rounds':'bdc_scoring_rounds';
        $eventTable=$test?'bdc_test_events':'bdc_events';
        $stmt=$pdo->prepare("SELECT r.id,r.round_type,r.division,e.name event_name FROM `{$roundTable}` r JOIN `{$eventTable}` e ON e.id=r.event_id WHERE r.event_id=:event ORDER BY r.id");
        $stmt->execute(['event'=>$eventId]);$rounds=$stmt->fetchAll();
        $deleted=0;$created=[];$mode=$test?'test':'live';
        foreach($rounds as $round){
            $roundId=(int)$round['id'];
            $label=(string)$round['event_name'].' · '.ucwords(str_replace('_',' ',(string)$round['division'])).' '.ucfirst((string)$round['round_type']).' · Final archive snapshot';
            $archiveId=self::create($pdo,$roundId,$test,$userId,'automatic','archive_snapshot',$label);
            $created[]=$archiveId;
            $latestEmergency=$pdo->prepare("SELECT id FROM bdc_scoring_backups WHERE round_id=:round AND data_mode=:mode AND backup_type='pre_restore' ORDER BY id DESC LIMIT 1");
            $latestEmergency->execute(['round'=>$roundId,'mode'=>$mode]);$emergencyId=(int)($latestEmergency->fetchColumn()?:0);
            $keep=[$archiveId];if($emergencyId>0)$keep[]=$emergencyId;
            $protected=$pdo->prepare("SELECT id FROM bdc_scoring_backups WHERE round_id=:round AND data_mode=:mode AND is_protected=1 AND action_name<>'archive_snapshot'");
            $protected->execute(['round'=>$roundId,'mode'=>$mode]);$keep=array_values(array_unique(array_merge($keep,array_map('intval',$protected->fetchAll(PDO::FETCH_COLUMN)))));
            $ph=implode(',',array_fill(0,count($keep),'?'));
            $delete=$pdo->prepare("DELETE FROM bdc_scoring_backups WHERE round_id=? AND data_mode=? AND id NOT IN ({$ph})");
            $delete->execute(array_merge([$roundId,$mode],$keep));$deleted+=$delete->rowCount();
        }
        return ['rounds'=>count($rounds),'archive_backup_ids'=>$created,'deleted_checkpoints'=>$deleted];
    }

    public static function judgeSubmissionCheckpoint(PDO $pdo,int $sessionId,bool $test):void
    {
        try{
            $sessions=$test?'bdc_test_scoring_judge_sessions':'bdc_scoring_judge_sessions';
            $judges=$test?'bdc_test_scoring_judges':'bdc_scoring_judges';
            $rounds=$test?'bdc_test_scoring_rounds':'bdc_scoring_rounds';
            $events=$test?'bdc_test_events':'bdc_events';
            $stmt=$pdo->prepare("SELECT s.round_id,j.judge_order,j.judge_name,e.name event_name FROM `{$sessions}` s JOIN `{$judges}` j ON j.id=s.judge_id JOIN `{$rounds}` r ON r.id=s.round_id JOIN `{$events}` e ON e.id=r.event_id WHERE s.id=:session");
            $stmt->execute(['session'=>$sessionId]);$row=$stmt->fetch();if(!$row)return;
            $judge='J'.(int)$row['judge_order'];
            $label=(string)$row['event_name'].' · After '.$judge.' submission · '.date('H:i:s');
            self::create($pdo,(int)$row['round_id'],$test,0,'automatic',strtolower($judge).'_submitted',$label);
        }catch(\Throwable $e){error_log('BDC scoring checkpoint failed: '.$e->getMessage());}
    }

    private static function tables(bool $test):array
    {
        $p=$test?'bdc_test_scoring_':'bdc_scoring_';
        return ['marks'=>$p.'marks','results'=>$p.'results','final_pairs'=>$p.'final_pairs','final_marks'=>$p.'final_marks','final_results'=>$p.'final_results','sessions'=>$p.'judge_sessions'];
    }

    private static function insertRows(PDO $pdo,string $table,array $rows,int $roundId):void
    {
        foreach($rows as $row){
            if(!is_array($row))continue;$row['round_id']=$roundId;$columns=array_keys($row);
            $quoted=array_map(static fn(string $column):string=>'`'.str_replace('`','',$column).'`',$columns);
            $placeholders=array_map(static fn(string $column):string=>':'.$column,$columns);
            $pdo->prepare("INSERT INTO `{$table}`(".implode(',',$quoted).") VALUES(".implode(',',$placeholders).")")->execute($row);
        }
    }

    private static function trimAutomatic(PDO $pdo,int $roundId,bool $test,int $keep):void
    {
        $stmt=$pdo->prepare("SELECT id FROM bdc_scoring_backups WHERE round_id=:round AND data_mode=:mode AND backup_type='automatic' ORDER BY id DESC LIMIT 18446744073709551615 OFFSET {$keep}");
        $stmt->execute(['round'=>$roundId,'mode'=>$test?'test':'live']);$ids=array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
        if($ids){$ph=implode(',',array_fill(0,count($ids),'?'));$pdo->prepare("DELETE FROM bdc_scoring_backups WHERE id IN ({$ph})")->execute($ids);}
    }
}
