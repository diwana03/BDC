<?php
declare(strict_types=1);
namespace App\Services;
use PDO; use RuntimeException;
final class SmartResultImportService {
 public function __construct(private PDO $pdo){  }
 public function analyse(string $path,string $kind): array {
  $ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));
  if($ext==='pdf') return ['format'=>'pdf','total_rows'=>0,'issues'=>[],'can_import_points'=>false];
  if($ext!=='csv') throw new RuntimeException('Only PDF and CSV files are supported.');
  [$headers,$rows,$total]=$this->readCsv($path,50); $normal=array_map([$this,'norm'],$headers); $issues=[];$can=false;
  if($kind==='points'){
   foreach([['competitor_exact_name','competitor_name','contestant_name','name'],['division'],['dance_role','role','dropdown'],['points','points_awarded']] as $aliases){ if(!$this->hasAny($normal,$aliases)) $issues[]='Missing column: '.$aliases[0]; }
   $can=!$issues;
  }
  if($kind==='final'&&!$this->hasAny($normal,['placement','place','rank','position'])) $issues[]='Placement column not detected. The file can still be stored in the repository.';
  return ['format'=>'csv','headers'=>$headers,'rows'=>$rows,'total_rows'=>$total,'issues'=>$issues,'can_import_points'=>$can];
 }
 public function importPoints(string $path,int $eventId,int $userId): array {
  [$headers,$rows]=$this->readCsv($path,0); $map=$this->map($headers);
  foreach(['name','division','role','points'] as $k) if(!isset($map[$k])) throw new RuntimeException('Points CSV is missing required data. Edit the CSV and upload again.');
  $errors=[];$imported=0;$skipped=0; $this->pdo->beginTransaction();
  try{
   foreach($rows as $i=>$row){ $line=$i+2; $name=trim((string)($row[$map['name']]??'')); $division=$this->division((string)($row[$map['division']]??'')); $role=$this->role((string)($row[$map['role']]??'')); $points=$this->number($row[$map['points']]??null);
    if($name===''||$division==='unknown'||$role==='unknown'||$points===null){$errors[]="Row $line: missing or invalid competitor, division, role or points.";continue;}
    $q=$this->pdo->prepare('SELECT id FROM bdc_competitors WHERE exact_name=:name LIMIT 1');$q->execute(['name'=>$name]);$cid=(int)$q->fetchColumn(); if(!$cid){$errors[]="Row $line: competitor not found: $name. Match or create the competitor manually first.";continue;}
    $hash=hash('sha256',implode('|',['smart_result_points',$eventId,$cid,$division,$role,(string)$points]));
    $q=$this->pdo->prepare("INSERT IGNORE INTO bdc_point_transactions(competitor_id,event_id,division,dance_role,points,source_type,source_row_hash,created_by,notes) VALUES(:c,:e,:d,:r,:p,'manual_adjustment',:h,:u,'Smart Result Import')");
    $q->execute(['c'=>$cid,'e'=>$eventId,'d'=>$division,'r'=>$role,'p'=>$points,'h'=>$hash,'u'=>$userId]); $q->rowCount()?$imported++:$skipped++;
   }
   if($errors) throw new RuntimeException(implode("\n",array_slice($errors,0,20))); $this->pdo->commit(); return ['imported'=>$imported,'skipped'=>$skipped];
  }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
 }
 private function readCsv(string $path,int $limit): array { $h=fopen($path,'rb'); if(!$h) throw new RuntimeException('Unable to read CSV.'); $headers=fgetcsv($h); if(!is_array($headers)) throw new RuntimeException('CSV header row is missing.'); $headers=array_map(fn($v)=>trim(preg_replace('/^\xEF\xBB\xBF/','',(string)$v)??(string)$v),$headers); $rows=[];$total=0; while(($v=fgetcsv($h))!==false){ if(!array_filter($v,fn($x)=>trim((string)$x)!==''))continue; $total++; if($limit===0||count($rows)<$limit){$v=array_pad($v,count($headers),'');$r=array_combine($headers,array_slice($v,0,count($headers)));if(is_array($r))$rows[]=$r;}} fclose($h); return [$headers,$rows,$total]; }
 private function map(array $headers): array {$m=[];foreach($headers as $h){$n=$this->norm($h);if(in_array($n,['competitor_exact_name','competitor_name','contestant_name','name'],true))$m['name']=$h;if($n==='division')$m['division']=$h;if(in_array($n,['dance_role','role','dropdown'],true))$m['role']=$h;if(in_array($n,['points','points_awarded'],true))$m['points']=$h;}return $m;}
 private function norm(string $v): string{return trim(preg_replace('/[^a-z0-9]+/','_',strtolower($v))??'','_');}
 private function hasAny(array $h,array $a): bool{foreach($a as $x)if(in_array($this->norm($x),$h,true))return true;return false;}
 private function division(string $v): string{$v=strtolower(trim($v));return str_contains($v,'inter')?'intermediate':(str_contains($v,'adv')?'advanced':(str_contains($v,'nov')?'novice':(str_contains($v,'all')?'all_star':'unknown')));}
 private function role(string $v): string{$v=strtolower(trim($v));return(str_contains($v,'lead')||$v==='l')?'leader':((str_contains($v,'follow')||$v==='f')?'follower':'unknown');}
 private function number(mixed $v): ?float{if($v===null||trim((string)$v)==='')return null;$n=filter_var(str_replace(',','',trim((string)$v)),FILTER_VALIDATE_FLOAT);return$n===false?null:(float)$n;}
}
