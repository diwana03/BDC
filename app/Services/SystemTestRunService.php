<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class SystemTestRunService
{
    public static function ensureSchema(PDO $pdo):void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_system_test_runs(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            access_request_id BIGINT UNSIGNED NULL,
            created_by BIGINT UNSIGNED NULL,
            run_type ENUM('isolated','parity') NOT NULL,
            idempotency_key CHAR(64) NOT NULL,
            status ENUM('running','passed','failed','error') NOT NULL DEFAULT 'running',
            live_event_id BIGINT UNSIGNED NULL,
            live_round_id BIGINT UNSIGNED NULL,
            test_event_id BIGINT UNSIGNED NULL,
            test_round_id BIGINT UNSIGNED NULL,
            report_json LONGTEXT NULL,
            error_message VARCHAR(1000) NULL,
            started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME NULL,
            UNIQUE KEY uq_system_test_idempotency(idempotency_key),
            INDEX idx_system_test_runs_type(run_type,id),
            INDEX idx_system_test_runs_access(access_request_id,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public static function begin(PDO $pdo,string $type,string $key,int $userId,?int $accessRequestId):array
    {
        self::ensureSchema($pdo);
        if(!in_array($type,['isolated','parity'],true)||!preg_match('/^[a-f0-9]{64}$/',$key))throw new RuntimeException('Invalid system test run request.');
        try{
            $q=$pdo->prepare("INSERT INTO bdc_system_test_runs(access_request_id,created_by,run_type,idempotency_key) VALUES(:access,:user,:type,:key)");
            $q->execute(['access'=>$accessRequestId,'user'=>$userId?:null,'type'=>$type,'key'=>$key]);
            return ['id'=>(int)$pdo->lastInsertId(),'created'=>true];
        }catch(\PDOException $e){
            if((string)$e->getCode()!=='23000')throw $e;
            $q=$pdo->prepare('SELECT id FROM bdc_system_test_runs WHERE idempotency_key=:key LIMIT 1');$q->execute(['key'=>$key]);
            $id=(int)$q->fetchColumn();if($id>0)return ['id'=>$id,'created'=>false];throw $e;
        }
    }

    public static function complete(PDO $pdo,int $id,array $report):void
    {
        $isParity=isset($report['live'],$report['test']);
        $q=$pdo->prepare("UPDATE bdc_system_test_runs SET status=:status,live_event_id=:live_event,live_round_id=:live_round,test_event_id=:test_event,test_round_id=:test_round,report_json=:report,error_message=NULL,completed_at=NOW() WHERE id=:id AND status='running'");
        $q->execute([
            'status'=>!empty($report['passed'])?'passed':'failed','live_event'=>$isParity?(int)$report['live']['event_id']:null,
            'live_round'=>$isParity?(int)$report['live']['round_id']:null,'test_event'=>$isParity?(int)$report['test']['event_id']:((int)($report['event_id']??0)?:null),
            'test_round'=>$isParity?(int)$report['test']['round_id']:((int)($report['round_id']??0)?:null),
            'report'=>json_encode($report,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),'id'=>$id,
        ]);
    }

    public static function fail(PDO $pdo,int $id,string $message):void
    {
        $pdo->prepare("UPDATE bdc_system_test_runs SET status='error',error_message=:error,completed_at=NOW() WHERE id=:id AND status='running'")
            ->execute(['error'=>substr($message,0,1000),'id'=>$id]);
    }

    public static function find(PDO $pdo,int $id,?int $accessRequestId=null):?array
    {
        self::ensureSchema($pdo);
        $sql='SELECT * FROM bdc_system_test_runs WHERE id=:id'.($accessRequestId!==null?' AND access_request_id=:access':'').' LIMIT 1';
        $q=$pdo->prepare($sql);$params=['id'=>$id];if($accessRequestId!==null)$params['access']=$accessRequestId;$q->execute($params);
        $row=$q->fetch(PDO::FETCH_ASSOC);if(!$row)return null;
        $row['report']=$row['report_json']?json_decode((string)$row['report_json'],true,512,JSON_THROW_ON_ERROR):null;return $row;
    }

    public static function recent(PDO $pdo,int $limit=20,?int $accessRequestId=null):array
    {
        self::ensureSchema($pdo);$limit=max(1,min(50,$limit));
        $sql="SELECT id,run_type,status,live_event_id,live_round_id,test_event_id,test_round_id,error_message,started_at,completed_at FROM bdc_system_test_runs".($accessRequestId!==null?' WHERE access_request_id=:access':'')." ORDER BY id DESC LIMIT {$limit}";
        $q=$pdo->prepare($sql);$q->execute($accessRequestId!==null?['access'=>$accessRequestId]:[]);return $q->fetchAll(PDO::FETCH_ASSOC);
    }
}
