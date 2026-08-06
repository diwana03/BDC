<?php
declare(strict_types=1);
namespace App\Services;
use PDO;

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
}
