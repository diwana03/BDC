<?php
declare(strict_types=1);
namespace App\Services;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

final class CompetitorIdentityService
{
 public static function scanHistorical(PDO $pdo):void{
  foreach($pdo->query("SELECT id FROM bdc_competitors WHERE status='active' AND career_group_id IS NULL ORDER BY id")->fetchAll(PDO::FETCH_COLUMN) as $id)self::inspect($pdo,(int)$id);
 }
 public static function normalise(?string $value,string $kind):string{
  $v=mb_strtolower(trim((string)$value));
  if($kind==='instagram')$v=ltrim(preg_replace('~^https?://(www\.)?instagram\.com/~','',$v)??$v,'@/');
  if($kind==='phone')$v=preg_replace('/\D+/','',$v)??'';
  return $v;
 }
 public static function inspect(PDO $pdo,int $id):void{
  $s=$pdo->prepare('SELECT * FROM bdc_competitors WHERE id=:id');$s->execute(['id'=>$id]);$c=$s->fetch();if(!$c)return;
  $matches=[];
  foreach(['email','phone','instagram'] as $kind){
   $value=self::normalise($c[$kind]??null,$kind);if($value==='')continue;
   $field=$kind;$q=$pdo->prepare("SELECT id,email,phone,instagram,dance_role,career_group_id FROM bdc_competitors WHERE id<>:id AND status='active' AND {$field} IS NOT NULL AND TRIM({$field})<>''");$q->execute(['id'=>$id]);
   foreach($q->fetchAll() as $other)if(($other['dance_role']!==$c['dance_role']||$other['dance_role']==='both'||$c['dance_role']==='both')&&self::normalise($other[$kind]??null,$kind)===$value)$matches[(int)$other['id']][]=$kind;
  }
  foreach($matches as $otherId=>$reasons){
   $a=min($id,$otherId);$b=max($id,$otherId);$certain=count($reasons)>=2||in_array('email',$reasons,true)||in_array('instagram',$reasons,true);
   $pdo->prepare("INSERT INTO bdc_competitor_link_suggestions(competitor_a_id,competitor_b_id,match_reason,confidence,status) VALUES(:a,:b,:r,:c,:s) ON DUPLICATE KEY UPDATE match_reason=VALUES(match_reason),confidence=VALUES(confidence)")->execute(['a'=>$a,'b'=>$b,'r'=>implode(' + ',$reasons),'c'=>$certain?'certain':'review','s'=>$certain?'auto_linked':'pending']);
   if($certain)self::link($pdo,$a,$b);
  }
 }
 public static function link(PDO $pdo,int $a,int $b):void{
  $q=$pdo->prepare('SELECT id,exact_name,career_group_id FROM bdc_competitors WHERE id IN(:a,:b) ORDER BY id');$q->execute(['a'=>$a,'b'=>$b]);$rows=$q->fetchAll();if(count($rows)!==2)return;
  $group=(int)($rows[0]['career_group_id']?:$rows[1]['career_group_id']);
  if(!$group){$i=$pdo->prepare('INSERT INTO bdc_competitor_career_groups(display_name) VALUES(:n)');$i->execute(['n'=>$rows[0]['exact_name']]);$group=(int)$pdo->lastInsertId();}
  foreach($rows as $row){$old=(int)($row['career_group_id']??0);if($old&&$old!==$group){$pdo->prepare('UPDATE bdc_competitors SET career_group_id=:g WHERE career_group_id=:old')->execute(['g'=>$group,'old'=>$old]);$pdo->prepare('DELETE FROM bdc_competitor_career_groups WHERE id=:old')->execute(['old'=>$old]);}}
  $pdo->prepare('UPDATE bdc_competitors SET career_group_id=:g WHERE id IN(:a,:b)')->execute(['g'=>$group,'a'=>$a,'b'=>$b]);
 }

 /** Canonical whitespace/case normalisation used when creating a new competitor. */
 public static function normaliseCompetitorName(string $name):string{
  return mb_strtolower(trim((string)preg_replace('/\s+/u',' ',$name)));
 }

 /**
  * Find an existing official competitor with the same normalised name/role or
  * create a new official BDC identity. This is the only scoring-registration
  * path that should allocate a new BDC ID.
  */
 public static function findOrCreateOfficial(PDO $pdo,string $exactName,string $danceRole,string $initialDivision='novice'):array{
  $exactName=trim($exactName);
  if($exactName==='')throw new RuntimeException('Competitor name is required.');
  if(!in_array($danceRole,['leader','follower','both'],true))throw new RuntimeException('Invalid competitor dance role.');
  // A scoring/event entry may request any competition category, but a new
  // identity remains provisional Novice until approved competition history
  // establishes career progression.
  $initialDivision=DivisionProgressionService::initialDivisionForUnapprovedEntry();
  $normalised=self::normaliseCompetitorName($exactName);

  $find=$pdo->prepare("SELECT id,bdc_id,exact_name,normalised_name,dance_role,current_division,status,is_historical FROM bdc_competitors WHERE normalised_name=:name AND dance_role IN(:role,'both') ORDER BY CASE WHEN dance_role=:preferred THEN 0 ELSE 1 END,id LIMIT 1");
  $find->execute(['name'=>$normalised,'role'=>$danceRole,'preferred'=>$danceRole]);
  $existing=$find->fetch(PDO::FETCH_ASSOC);
  if($existing)return $existing+['created'=>false];

  $ownsTransaction=!$pdo->inTransaction();
  if($ownsTransaction)$pdo->beginTransaction();
  try{
   // Recheck after entering transaction context.
   $find->execute(['name'=>$normalised,'role'=>$danceRole,'preferred'=>$danceRole]);
   $existing=$find->fetch(PDO::FETCH_ASSOC);
   if($existing){if($ownsTransaction)$pdo->commit();return $existing+['created'=>false];}

   $insert=$pdo->prepare("INSERT INTO bdc_competitors(bdc_id,exact_name,normalised_name,dance_role,current_division,status,is_historical) VALUES(:bdc,:name,:normalised,:role,:division,'pending',0)");
   $lastError=null;
   for($attempt=0;$attempt<5;$attempt++){
    $next=(int)$pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(bdc_id,5) AS UNSIGNED)),0)+1 FROM bdc_competitors WHERE bdc_id LIKE 'BDC-%'")->fetchColumn();
    $bdcId='BDC-'.str_pad((string)$next,6,'0',STR_PAD_LEFT);
    try{
     $insert->execute(['bdc'=>$bdcId,'name'=>$exactName,'normalised'=>$normalised,'role'=>$danceRole,'division'=>$initialDivision]);
     $row=['id'=>(int)$pdo->lastInsertId(),'bdc_id'=>$bdcId,'exact_name'=>$exactName,'normalised_name'=>$normalised,'dance_role'=>$danceRole,'current_division'=>$initialDivision,'status'=>'pending','is_historical'=>0,'created'=>true];
     if($ownsTransaction)$pdo->commit();
     return $row;
    }catch(PDOException $e){
     $lastError=$e;
     if((string)$e->getCode()!=='23000')throw $e;
     $find->execute(['name'=>$normalised,'role'=>$danceRole,'preferred'=>$danceRole]);
     $existing=$find->fetch(PDO::FETCH_ASSOC);
     if($existing){if($ownsTransaction)$pdo->commit();return $existing+['created'=>false];}
     // Otherwise another request probably consumed the same BDC number; retry.
    }
   }
   throw new RuntimeException('Could not allocate a unique BDC ID after multiple attempts.',0,$lastError);
  }catch(Throwable $e){if($ownsTransaction&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
 }

 /** Mirror an official identity into isolated Test data without copying photos/profile metadata. */
 public static function mirrorOfficialToTest(PDO $pdo,array $competitor):void{
  $stmt=$pdo->prepare("INSERT INTO bdc_test_competitors(id,bdc_id,exact_name,normalised_name,dance_role,current_division,status,is_historical) VALUES(:id,:bdc,:name,:normalised,:role,:division,:status,:historical) ON DUPLICATE KEY UPDATE bdc_id=VALUES(bdc_id),exact_name=VALUES(exact_name),normalised_name=VALUES(normalised_name),dance_role=VALUES(dance_role),current_division=VALUES(current_division),status=VALUES(status),is_historical=VALUES(is_historical)");
  $stmt->execute([
   'id'=>(int)$competitor['id'],
   'bdc'=>(string)$competitor['bdc_id'],
   'name'=>(string)$competitor['exact_name'],
   'normalised'=>(string)($competitor['normalised_name']??self::normaliseCompetitorName((string)$competitor['exact_name'])),
   'role'=>(string)$competitor['dance_role'],
   'division'=>(string)($competitor['current_division']??'novice'),
   'status'=>(string)($competitor['status']??'pending'),
   'historical'=>(int)($competitor['is_historical']??0),
  ]);
 }

 /** Create a provisional identity in isolated Test data only. */
 public static function findOrCreateTest(PDO $pdo,string $exactName,string $danceRole,string $initialDivision='novice'):array{
  $exactName=trim($exactName);if($exactName==='')throw new RuntimeException('Competitor name is required.');
  if(!in_array($danceRole,['leader','follower','both'],true))throw new RuntimeException('Invalid competitor dance role.');
  $normalised=self::normaliseCompetitorName($exactName);
  $find=$pdo->prepare("SELECT * FROM bdc_test_competitors WHERE normalised_name=:name AND dance_role IN(:role,'both') ORDER BY CASE WHEN dance_role=:preferred THEN 0 ELSE 1 END,id LIMIT 1");
  $find->execute(['name'=>$normalised,'role'=>$danceRole,'preferred'=>$danceRole]);if($row=$find->fetch(PDO::FETCH_ASSOC))return $row+['created'=>false,'test_only'=>true];
  $division=DivisionProgressionService::normaliseDivision($initialDivision);if($division==='unknown')$division='novice';
  $next=(int)$pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(bdc_id,5) AS UNSIGNED)),0)+1 FROM bdc_test_competitors WHERE bdc_id LIKE 'BDC-%'")->fetchColumn();
  $bdcId='BDC-'.str_pad((string)$next,6,'0',STR_PAD_LEFT);
  $insert=$pdo->prepare("INSERT INTO bdc_test_competitors(bdc_id,exact_name,normalised_name,dance_role,current_division,status,is_historical) VALUES(:bdc,:name,:normalised,:role,:division,'pending',0)");
  $insert->execute(['bdc'=>$bdcId,'name'=>$exactName,'normalised'=>$normalised,'role'=>$danceRole,'division'=>$division]);
  return ['id'=>(int)$pdo->lastInsertId(),'bdc_id'=>$bdcId,'exact_name'=>$exactName,'normalised_name'=>$normalised,'dance_role'=>$danceRole,'current_division'=>$division,'status'=>'pending','is_historical'=>0,'created'=>true,'test_only'=>true];
 }
}
