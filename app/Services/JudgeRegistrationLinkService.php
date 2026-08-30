<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Config;use PDO;
final class JudgeRegistrationLinkService{
 public static function ensure(PDO $pdo):void{$pdo->exec("CREATE TABLE IF NOT EXISTS bdc_judge_registration_links(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,token_hash CHAR(64) NOT NULL,token_value CHAR(64) NOT NULL,status ENUM('active','revoked','expired') NOT NULL DEFAULT 'active',expires_at DATETIME NOT NULL,created_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE INDEX uq_judge_registration_token(token_hash),INDEX idx_judge_registration_active(status,expires_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");}
 public static function generate(PDO $pdo,?int $user):array{self::ensure($pdo);$pdo->exec("UPDATE bdc_judge_registration_links SET status='revoked' WHERE status='active'");$token=bin2hex(random_bytes(32));$pdo->prepare("INSERT INTO bdc_judge_registration_links(token_hash,token_value,status,expires_at,created_by) VALUES(:hash,:token,'active',DATE_ADD(NOW(),INTERVAL 12 HOUR),:user)")->execute(['hash'=>hash('sha256',$token),'token'=>$token,'user'=>$user]);return ['token'=>$token,'url'=>self::url($token)];}
 public static function active(PDO $pdo):?array{self::ensure($pdo);$pdo->exec("UPDATE bdc_judge_registration_links SET status='expired' WHERE status='active' AND expires_at<=NOW()");$row=$pdo->query("SELECT token_value,expires_at FROM bdc_judge_registration_links WHERE status='active' AND expires_at>NOW() ORDER BY id DESC LIMIT 1")->fetch();if(!$row)return null;$row['url']=self::url((string)$row['token_value']);return $row;}
 public static function valid(PDO $pdo,string $token):bool{self::ensure($pdo);if(!preg_match('/^[a-f0-9]{64}$/',$token))return false;$q=$pdo->prepare("SELECT 1 FROM bdc_judge_registration_links WHERE token_hash=:hash AND status='active' AND expires_at>NOW() LIMIT 1");$q->execute(['hash'=>hash('sha256',$token)]);return (bool)$q->fetchColumn();}
 public static function url(string $token):string{return absolute_url('judge-profile/?invite='.rawurlencode($token));}
}
