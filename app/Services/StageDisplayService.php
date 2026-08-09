<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Config;
use PDO;
use RuntimeException;
final class StageDisplayService{
 public static function generate(PDO $pdo,int $roundId,bool $test,int $userId):string{
  $roundTable=$test?'bdc_test_scoring_rounds':'bdc_scoring_rounds';
  $s=$pdo->prepare("SELECT id FROM {$roundTable} WHERE id=:id LIMIT 1");$s->execute(['id'=>$roundId]);if(!$s->fetchColumn())throw new RuntimeException('Round not found.');
  self::ensure($pdo);
  $token=bin2hex(random_bytes(24));$hash=hash('sha256',$token);$mode=$test?'test':'real';
  $pdo->prepare("INSERT INTO bdc_stage_display_links(round_id,data_mode,token_hash,token_hint,is_enabled,created_by) VALUES(:round,:mode,:hash,:hint,1,:user) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),token_hint=VALUES(token_hint),is_enabled=1,created_by=VALUES(created_by),updated_at=NOW()")
   ->execute(['round'=>$roundId,'mode'=>$mode,'hash'=>$hash,'hint'=>substr($token,0,8),'user'=>$userId?:null]);return $token;
 }
 public static function byToken(PDO $pdo,string $token):?array{if(!preg_match('/^[a-f0-9]{48}$/',$token))return null;self::ensure($pdo);$s=$pdo->prepare("SELECT * FROM bdc_stage_display_links WHERE token_hash=:hash AND is_enabled=1 LIMIT 1");$s->execute(['hash'=>hash('sha256',$token)]);return $s->fetch()?:null;}
 public static function payload(PDO $pdo,array $link):array{
  $test=($link['data_mode']??'real')==='test';$prefix=$test?'bdc_test_':'bdc_';$roundTable=$prefix.'scoring_rounds';$eventTable=$prefix.'events';$entryTable=$prefix.'scoring_entries';$compTable=$prefix.'competitors';
  $s=$pdo->prepare("SELECT r.*,e.name event_name,e.event_date FROM {$roundTable} r JOIN {$eventTable} e ON e.id=r.event_id WHERE r.id=:id LIMIT 1");$s->execute(['id'=>$link['round_id']]);$round=$s->fetch();if(!$round)throw new RuntimeException('Display round is unavailable.');
  $s=$pdo->prepare("SELECT se.id,se.dance_role,se.bib_number,se.display_name,c.bdc_id,c.country,c.photo_url FROM {$entryTable} se LEFT JOIN {$compTable} c ON c.id=se.competitor_id WHERE se.round_id=:round AND se.entry_status='active' ORDER BY se.dance_role,se.bib_number IS NULL,se.bib_number,se.display_name");$s->execute(['round'=>$link['round_id']]);
  return ['test'=>$test,'round'=>$round,'competitors'=>$s->fetchAll()];
 }
 public static function publicUrl(string $token):string{$path=url('stage-display/?token='.rawurlencode($token));$app=rtrim((string)Config::get('app.url',''),'/');if($app==='')return $path;$p=parse_url($app);if(!is_array($p)||!isset($p['scheme'],$p['host']))return $path;return $p['scheme'].'://'.$p['host'].(isset($p['port'])?':'.(int)$p['port']:'').$path;}
 public static function ensure(PDO $pdo):void{$pdo->exec("CREATE TABLE IF NOT EXISTS bdc_stage_display_links(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,round_id BIGINT UNSIGNED NOT NULL,data_mode ENUM('real','test') NOT NULL DEFAULT 'real',token_hash CHAR(64) NOT NULL,token_hint VARCHAR(12) NOT NULL,is_enabled TINYINT(1) NOT NULL DEFAULT 1,created_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE INDEX uq_stage_round_mode(round_id,data_mode),UNIQUE INDEX uq_stage_token(token_hash)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");}
}