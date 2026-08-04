<?php
declare(strict_types=1);
namespace App\Services;
use PDO;

final class AutosaveService
{
 public static function save(PDO $pdo,int $userId,string $entityType,int $entityId,string $section,array $payload):array
 {
  $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  if($json===false) throw new \RuntimeException('Could not encode autosave data.');
  $stmt=$pdo->prepare("INSERT INTO bdc_autosave_drafts(user_id,entity_type,entity_id,section_key,payload_json,revision_number,status,last_saved_at) VALUES(:u,:t,:e,:s,:p,1,'active',NOW()) ON DUPLICATE KEY UPDATE payload_json=VALUES(payload_json),revision_number=revision_number+1,status='active',last_saved_at=NOW(),updated_at=NOW()");
  $stmt->execute(['u'=>$userId,'t'=>$entityType,'e'=>$entityId,'s'=>$section,'p'=>$json]);
  return self::latest($pdo,$userId,$entityType,$entityId,$section)??[];
 }
 public static function latest(PDO $pdo,int $userId,string $entityType,int $entityId,string $section='main'):?array
 {
  $stmt=$pdo->prepare("SELECT * FROM bdc_autosave_drafts WHERE user_id=:u AND entity_type=:t AND entity_id=:e AND section_key=:s AND status='active' LIMIT 1");
  $stmt->execute(['u'=>$userId,'t'=>$entityType,'e'=>$entityId,'s'=>$section]);
  $row=$stmt->fetch();
  if(!$row) return null;
  $row['payload']=json_decode((string)$row['payload_json'],true)?:[];
  return $row;
 }
 public static function discard(PDO $pdo,int $userId,int $id):void
 {
  $stmt=$pdo->prepare("UPDATE bdc_autosave_drafts SET status='discarded',updated_at=NOW() WHERE id=:id AND user_id=:u");
  $stmt->execute(['id'=>$id,'u'=>$userId]);
 }
 public static function listActive(PDO $pdo,int $userId):array
 {
  $stmt=$pdo->prepare("SELECT d.*,e.name event_name,r.division,r.round_type FROM bdc_autosave_drafts d LEFT JOIN bdc_scoring_rounds r ON d.entity_type='scoring_round' AND r.id=d.entity_id LEFT JOIN bdc_events e ON e.id=r.event_id WHERE d.user_id=:u AND d.status='active' ORDER BY d.last_saved_at DESC");
  $stmt->execute(['u'=>$userId]);
  return $stmt->fetchAll();
 }
}
