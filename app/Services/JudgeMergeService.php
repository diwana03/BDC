<?php
declare(strict_types=1);
namespace App\Services;
use PDO;use RuntimeException;use Throwable;

final class JudgeMergeService
{
 public static function merge(PDO $pdo,int $keepId,int $removeId,int $userId):array
 {
  if($keepId<1||$removeId<1||$keepId===$removeId)throw new RuntimeException('Choose two different judge profiles.');
  $q=$pdo->prepare('SELECT * FROM bdc_judges WHERE id IN(:keep,:remove) ORDER BY id');$q->execute(['keep'=>$keepId,'remove'=>$removeId]);$profiles=[];foreach($q->fetchAll() as $row)$profiles[(int)$row['id']]=$row;if(!isset($profiles[$keepId],$profiles[$removeId]))throw new RuntimeException('One of the judge profiles no longer exists.');
  $links=[['bdc_scoring_judges','round_id'],['bdc_test_scoring_judges','round_id'],['bdc_dance_cup_judges','competition_id'],['bdc_test_dance_cup_judges','competition_id']];
  foreach($links as [$table,$scope]){if(!self::table($pdo,$table)||!self::column($pdo,$table,'judge_id'))continue;$c=$pdo->prepare("SELECT COUNT(*) FROM {$table} a JOIN {$table} b ON b.{$scope}=a.{$scope} AND b.judge_id=:keep WHERE a.judge_id=:remove");$c->execute(['keep'=>$keepId,'remove'=>$removeId]);if((int)$c->fetchColumn()>0)throw new RuntimeException('Both judge profiles are assigned to the same scoring panel. Remove the duplicate panel assignment first, then merge.');}
  $pdo->beginTransaction();try{
   $moved=[];foreach($links as [$table]){if(!self::table($pdo,$table)||!self::column($pdo,$table,'judge_id'))continue;$u=$pdo->prepare("UPDATE {$table} SET judge_id=:keep WHERE judge_id=:remove");$u->execute(['keep'=>$keepId,'remove'=>$removeId]);$moved[$table]=$u->rowCount();}
   if(self::table($pdo,'bdc_judge_profile_update_links')){$pdo->prepare('DELETE FROM bdc_judge_profile_update_links WHERE judge_id=:remove')->execute(['remove'=>$removeId]);}
   $pdo->prepare("UPDATE bdc_judges k JOIN bdc_judges d ON d.id=:remove SET k.display_name=COALESCE(NULLIF(k.display_name,''),d.display_name),k.country=COALESCE(NULLIF(k.country,''),d.country),k.country_code=COALESCE(NULLIF(k.country_code,''),d.country_code),k.city=COALESCE(NULLIF(k.city,''),d.city),k.photo_url=COALESCE(NULLIF(k.photo_url,''),d.photo_url),k.instagram=COALESCE(NULLIF(k.instagram,''),d.instagram),k.email=COALESCE(NULLIF(k.email,''),d.email),k.phone=COALESCE(NULLIF(k.phone,''),d.phone),k.whatsapp=COALESCE(NULLIF(k.whatsapp,''),d.whatsapp),k.notes=TRIM(CONCAT(COALESCE(k.notes,''),'\nMerged duplicate ',d.judge_code,' / ',d.full_name,' on ',NOW())) WHERE k.id=:keep")->execute(['keep'=>$keepId,'remove'=>$removeId]);
   $pdo->prepare('DELETE FROM bdc_judges WHERE id=:remove')->execute(['remove'=>$removeId]);
   \App\Core\Auth::audit($userId,'judge_merged',['kept_id'=>$keepId,'removed_id'=>$removeId,'moved_rows'=>$moved],'judge',$keepId);$pdo->commit();return ['kept'=>$profiles[$keepId],'removed'=>$profiles[$removeId],'moved'=>$moved];
  }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 }
 private static function table(PDO $pdo,string $table):bool{$q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table');$q->execute(['table'=>$table]);return(int)$q->fetchColumn()>0;}
 private static function column(PDO $pdo,string $table,string $column):bool{$q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table AND column_name=:column');$q->execute(['table'=>$table,'column'=>$column]);return(int)$q->fetchColumn()>0;}
}
