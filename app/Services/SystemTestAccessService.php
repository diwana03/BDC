<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class SystemTestAccessService
{
    public static function ensureSchema(PDO $pdo):void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_system_test_access_requests(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            selector CHAR(24) NOT NULL,
            token_hash CHAR(64) NOT NULL,
            token_hint VARCHAR(12) NOT NULL,
            requester_ip_hash CHAR(64) NOT NULL,
            requester_agent_hash CHAR(64) NOT NULL,
            status ENUM('pending','approved','denied','used','expired') NOT NULL DEFAULT 'pending',
            requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            approved_by BIGINT UNSIGNED NULL,
            approved_at DATETIME NULL,
            used_at DATETIME NULL,
            UNIQUE KEY uq_system_test_selector(selector),
            INDEX idx_system_test_pending(status,expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("UPDATE bdc_system_test_access_requests SET status='expired' WHERE status IN('pending','approved') AND expires_at<NOW()");
    }

    public static function request(PDO $pdo,string $ip,string $agent):string
    {
        self::ensureSchema($pdo);
        $ipHash=hash('sha256',$ip);
        $limit=$pdo->prepare("SELECT COUNT(*) FROM bdc_system_test_access_requests WHERE requester_ip_hash=:ip AND requested_at>=DATE_SUB(NOW(),INTERVAL 1 HOUR)");
        $limit->execute(['ip'=>$ipHash]);
        if((int)$limit->fetchColumn()>=3)throw new RuntimeException('Too many test-access requests. Wait one hour and try again.');
        $pdo->prepare("UPDATE bdc_system_test_access_requests SET status='expired' WHERE requester_ip_hash=:ip AND status='pending'")->execute(['ip'=>$ipHash]);
        $selector=bin2hex(random_bytes(12));$secret=bin2hex(random_bytes(32));
        $pdo->prepare("INSERT INTO bdc_system_test_access_requests(selector,token_hash,token_hint,requester_ip_hash,requester_agent_hash,expires_at) VALUES(:selector,:hash,:hint,:ip,:agent,DATE_ADD(NOW(),INTERVAL 15 MINUTE))")
            ->execute(['selector'=>$selector,'hash'=>hash('sha256',$secret),'hint'=>substr($secret,0,8),'ip'=>$ipHash,'agent'=>hash('sha256',$agent)]);
        return $selector.'.'.$secret;
    }

    public static function status(PDO $pdo,string $token):array
    {
        self::ensureSchema($pdo);
        [$selector,$secret]=self::parts($token);
        $q=$pdo->prepare('SELECT id,status,requested_at,expires_at,approved_at FROM bdc_system_test_access_requests WHERE selector=:selector AND token_hash=:hash LIMIT 1');
        $q->execute(['selector'=>$selector,'hash'=>hash('sha256',$secret)]);
        return $q->fetch(PDO::FETCH_ASSOC)?:['status'=>'invalid'];
    }

    public static function pending(PDO $pdo):array
    {
        self::ensureSchema($pdo);
        return $pdo->query("SELECT id,selector,token_hint,requested_at,expires_at FROM bdc_system_test_access_requests WHERE status='pending' AND expires_at>=NOW() ORDER BY requested_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function decide(PDO $pdo,int $id,bool $approve,int $userId):void
    {
        self::ensureSchema($pdo);
        $status=$approve?'approved':'denied';
        $q=$pdo->prepare("UPDATE bdc_system_test_access_requests SET status=:status,approved_by=:user,approved_at=NOW(),expires_at=IF(:approved=1,DATE_ADD(NOW(),INTERVAL 20 MINUTE),expires_at) WHERE id=:id AND status='pending' AND expires_at>=NOW()");
        $q->execute(['status'=>$status,'user'=>$userId,'approved'=>$approve?1:0,'id'=>$id]);
        if($q->rowCount()!==1)throw new RuntimeException('Pending test-access request was not found or has expired.');
    }

    public static function verifyApproved(PDO $pdo,string $token,string $agent):?array
    {
        if($token==='')return null;
        $row=self::status($pdo,$token);
        if(($row['status']??'')!=='approved')return null;
        [$selector]=self::parts($token);
        $q=$pdo->prepare("SELECT id,approved_by,expires_at FROM bdc_system_test_access_requests WHERE selector=:selector AND requester_agent_hash=:agent AND status='approved' AND expires_at>=NOW() LIMIT 1");
        $q->execute(['selector'=>$selector,'agent'=>hash('sha256',$agent)]);
        return $q->fetch(PDO::FETCH_ASSOC)?:null;
    }

    public static function consume(PDO $pdo,int $id):void
    {
        $pdo->prepare("UPDATE bdc_system_test_access_requests SET status='used',used_at=NOW() WHERE id=:id AND status='approved'")->execute(['id'=>$id]);
    }

    private static function parts(string $token):array
    {
        if(!preg_match('/^([a-f0-9]{24})\.([a-f0-9]{64})$/',$token,$match))return ['',''];
        return [$match[1],$match[2]];
    }
}
