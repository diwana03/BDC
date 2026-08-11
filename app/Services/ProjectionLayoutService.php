<?php
declare(strict_types=1);
namespace App\Services;
final class ProjectionLayoutService{
 public const PRESETS=['16:9'=>[16,9],'9:16'=>[9,16],'4:3'=>[4,3],'16:10'=>[16,10],'21:9'=>[21,9],'32:9'=>[32,9],'1:1'=>[1,1]];
 public static function resolve(string $format,int $count,string $density='maximum',?int $width=null,?int $height=null):array{
  if($format==='custom'&&$width&&$height){$ratio=$width/$height;$label=$width.'×'.$height;}else{[$w,$h]=self::PRESETS[$format]??self::PRESETS['16:9'];$ratio=$w/$h;$label=array_key_exists($format,self::PRESETS)?$format:'16:9';}
  $count=max(1,$count);$max=$density==='large'?24:($density==='auto'?40:60);if($ratio>=3)$max=$density==='maximum'?80:$max;if($ratio<0.8)$max=$density==='maximum'?40:min($max,30);
  $perPage=min($count,$max);$best=[1,$perPage,PHP_FLOAT_MAX];
  for($rows=1;$rows<=$perPage;$rows++){$cols=(int)ceil($perPage/$rows);$gridRatio=$cols/$rows;$score=abs(log(max(.01,$gridRatio/$ratio)));if($score<$best[2])$best=[$rows,$cols,$score];}
  [$rows,$cols]=$best;$capacity=$rows*$cols;$pages=(int)ceil($count/$capacity);
  return ['format'=>$label,'ratio'=>$ratio,'density'=>$density,'rows'=>$rows,'columns'=>$cols,'capacity'=>$capacity,'pages'=>$pages,'count'=>$count];
 }
 public static function formats():array{return array_keys(self::PRESETS)+['custom'=>'custom'];}
}