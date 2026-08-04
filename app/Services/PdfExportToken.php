<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
final class PdfExportToken{
 private const KEY='pdf_export_secret';
 private static function secret(PDO $pdo):string{
  $s=$pdo->prepare("SELECT setting_value FROM bdc_settings WHERE setting_key=:k");$s->execute(['k'=>self::KEY]);$v=(string)$s->fetchColumn();
  if($v===''){$v=bin2hex(random_bytes(32));$pdo->prepare("INSERT INTO bdc_settings(setting_key,setting_value) VALUES(:k,:v) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute(['k'=>self::KEY,'v'=>$v]);}
  return $v;
 }
 public static function issue(PDO $pdo,string $type,int $roundId):array{
  $exp=time()+300;$sig=hash_hmac('sha256',$type.'|'.$roundId.'|'.$exp,self::secret($pdo));
  return ['round_id'=>$roundId,'pdf_expires'=>$exp,'pdf_token'=>$sig];
 }
 public static function verify(PDO $pdo,string $type,int $roundId,array $q):bool{
  $exp=(int)($q['pdf_expires']??0);$token=(string)($q['pdf_token']??'');
  if($exp<time()||$exp>time()+900||$token==='')return false;
  return hash_equals(hash_hmac('sha256',$type.'|'.$roundId.'|'.$exp,self::secret($pdo)),$token);
 }
}
