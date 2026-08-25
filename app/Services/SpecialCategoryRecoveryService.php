<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;
use ZipArchive;

final class SpecialCategoryRecoveryService
{
    private const SPECIAL=['bachata_rising','bachata_open','bachata_invitational','salsa_rising','salsa_open'];

    /** @return array{candidates:int,restored:int,skipped:int} */
    public static function recoverManualAssignments(PDO $pdo,bool $apply=true):array
    {
        self::ensureSchema($pdo);
        $rows=$pdo->query("SELECT id,entity_id,details_json,created_at FROM bdc_audit_logs WHERE entity_type='competitor' AND action IN ('competitor_created','competitor_updated') AND entity_id IS NOT NULL ORDER BY id")->fetchAll();
        $latest=[];
        foreach($rows as $row){
            $details=json_decode((string)$row['details_json'],true);
            if(!is_array($details))continue;
            $dance=in_array((string)($details['dance_style']??''),['bachata','salsa'],true)?(string)$details['dance_style']:'bachata';
            $division=strtolower(trim((string)($details['division']??'')));
            if($division==='')continue;
            $role=in_array((string)($details['role']??''),['leader','follower','both','unknown'],true)?(string)$details['role']:'unknown';
            $latest[(int)$row['entity_id'].'|'.$dance]=['audit_id'=>(int)$row['id'],'competitor_id'=>(int)$row['entity_id'],'dance_style'=>$dance,'dance_role'=>$role,'division'=>$division,'created_at'=>(string)$row['created_at']];
        }
        $candidates=array_values(array_filter($latest,static fn(array $row):bool=>in_array($row['division'],self::SPECIAL,true)));
        $restored=0;$skipped=0;
        $exists=$pdo->prepare('SELECT COUNT(*) FROM bdc_competitors WHERE id=:id');
        $profile=$pdo->prepare("INSERT INTO bdc_competitor_discipline_profiles(competitor_id,dance_style,dance_role,current_division) SELECT :competitor,:dance,:role,:division FROM bdc_competitors c WHERE c.id=:source ON DUPLICATE KEY UPDATE current_division=VALUES(current_division),updated_at=NOW()");
        $legacy=$pdo->prepare('UPDATE bdc_competitors SET current_division=:division WHERE id=:competitor');
        $snapshot=$pdo->prepare("INSERT INTO bdc_special_category_recovery(audit_log_id,competitor_id,dance_style,recovered_category,audit_created_at,applied_at) VALUES(:audit,:competitor,:dance,:category,:created,IF(:applied=1,NOW(),NULL)) ON DUPLICATE KEY UPDATE applied_at=IF(:applied_update=1,COALESCE(applied_at,NOW()),applied_at)");
        foreach($candidates as $candidate){
            $exists->execute(['id'=>$candidate['competitor_id']]);
            if(!(int)$exists->fetchColumn()){$skipped++;continue;}
            $snapshot->execute(['audit'=>$candidate['audit_id'],'competitor'=>$candidate['competitor_id'],'dance'=>$candidate['dance_style'],'category'=>$candidate['division'],'created'=>$candidate['created_at'],'applied'=>$apply?1:0,'applied_update'=>$apply?1:0]);
            if(!$apply)continue;
            $profile->execute(['competitor'=>$candidate['competitor_id'],'dance'=>$candidate['dance_style'],'role'=>$candidate['dance_role'],'division'=>$candidate['division'],'source'=>$candidate['competitor_id']]);
            if($candidate['dance_style']==='bachata')$legacy->execute(['division'=>$candidate['division'],'competitor'=>$candidate['competitor_id']]);
            $restored++;
        }
        return ['candidates'=>count($candidates),'restored'=>$restored,'skipped'=>$skipped];
    }

    public static function ensureSchema(PDO $pdo):void
    {
        $values="'novice','intermediate','advanced','bachata_rising','bachata_open','bachata_invitational','salsa_rising','salsa_open','semi_pro','pro','professional','all_star','unknown'";
        foreach(['bdc_competitors','bdc_competitor_discipline_profiles','bdc_test_competitors'] as $table){
            $exists=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND COLUMN_NAME='current_division'");$exists->execute(['table'=>$table]);
            if((int)$exists->fetchColumn()===1)$pdo->exec("ALTER TABLE `{$table}` MODIFY current_division ENUM({$values}) NOT NULL DEFAULT 'unknown'");
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_special_category_recovery(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,audit_log_id BIGINT UNSIGNED NULL,competitor_id BIGINT UNSIGNED NOT NULL,dance_style ENUM('bachata','salsa') NOT NULL,recovered_category VARCHAR(40) NOT NULL,audit_created_at DATETIME NULL,source_kind VARCHAR(20) NOT NULL DEFAULT 'audit',source_name VARCHAR(255) NULL,before_category VARCHAR(40) NULL,applied_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_special_recovery_audit(audit_log_id),INDEX idx_special_recovery_competitor(competitor_id,dance_style)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        foreach(["ALTER TABLE bdc_special_category_recovery MODIFY audit_log_id BIGINT UNSIGNED NULL","ALTER TABLE bdc_special_category_recovery MODIFY audit_created_at DATETIME NULL","ALTER TABLE bdc_special_category_recovery ADD COLUMN source_kind VARCHAR(20) NOT NULL DEFAULT 'audit' AFTER audit_created_at","ALTER TABLE bdc_special_category_recovery ADD COLUMN source_name VARCHAR(255) NULL AFTER source_kind","ALTER TABLE bdc_special_category_recovery ADD COLUMN before_category VARCHAR(40) NULL AFTER source_name"] as $sql){try{$pdo->exec($sql);}catch(\Throwable){}}
    }

    /** @return array<int,array<string,mixed>> */
    public static function availableBackups():array
    {
        return array_values(array_filter((new BackupService())->listBackups(),static fn(array $row):bool=>in_array($row['type'],['database','full'],true)));
    }

    /** @return array{backup:string,total:int,matched:int,recoverable:int,already_special:int,missing:int,candidates:array<int,array<string,mixed>>} */
    public static function previewBackup(PDO $pdo,string $type,string $name):array
    {
        $sql=self::backupSql($type,$name);$competitors=self::tableRows($sql,'bdc_competitors');$profiles=self::tableRows($sql,'bdc_competitor_discipline_profiles');
        $identity=[];foreach($competitors as $row)$identity[(int)($row['id']??0)]=['bdc_id'=>(string)($row['bdc_id']??''),'role'=>(string)($row['dance_role']??'unknown'),'division'=>(string)($row['current_division']??'unknown')];
        $candidates=[];
        foreach($profiles as $row){$division=strtolower((string)($row['current_division']??''));if(!in_array($division,self::SPECIAL,true))continue;$oldId=(int)($row['competitor_id']??0);$dance=in_array((string)($row['dance_style']??''),['bachata','salsa'],true)?(string)$row['dance_style']:'bachata';$candidates[$oldId.'|'.$dance]=['backup_competitor_id'=>$oldId,'bdc_id'=>(string)($identity[$oldId]['bdc_id']??''),'dance_style'=>$dance,'dance_role'=>(string)($row['dance_role']??$identity[$oldId]['role']??'unknown'),'category'=>$division];}
        foreach($identity as $oldId=>$row){$division=strtolower((string)$row['division']);if(!in_array($division,self::SPECIAL,true)||isset($candidates[$oldId.'|bachata']))continue;$candidates[$oldId.'|bachata']=['backup_competitor_id'=>$oldId,'bdc_id'=>$row['bdc_id'],'dance_style'=>'bachata','dance_role'=>$row['role'],'category'=>$division];}
        $findByBdc=$pdo->prepare("SELECT id,bdc_id,exact_name FROM bdc_competitors WHERE bdc_id<>'' AND bdc_id=:bdc LIMIT 1");
        $findById=$pdo->prepare('SELECT id,bdc_id,exact_name FROM bdc_competitors WHERE id=:legacy LIMIT 1');
        $findCategory=$pdo->prepare('SELECT current_division FROM bdc_competitor_discipline_profiles WHERE competitor_id=:competitor AND dance_style=:dance LIMIT 1');
        $matched=0;$recoverable=0;$alreadySpecial=0;$missing=0;
        foreach($candidates as &$candidate){
            if($candidate['bdc_id']!==''){$findByBdc->execute(['bdc'=>$candidate['bdc_id']]);$current=$findByBdc->fetch();}
            else{$findById->execute(['legacy'=>$candidate['backup_competitor_id']]);$current=$findById->fetch();}
            if($current){
                $candidate['competitor_id']=(int)$current['id'];$candidate['exact_name']=(string)$current['exact_name'];$matched++;
                $findCategory->execute(['competitor'=>$candidate['competitor_id'],'dance'=>$candidate['dance_style']]);$candidate['current_category']=(string)($findCategory->fetchColumn()?:'unknown');
                $candidate['needs_restore']=!in_array($candidate['current_category'],self::SPECIAL,true);
                if($candidate['needs_restore'])$recoverable++;else$alreadySpecial++;
            }else{$candidate['competitor_id']=0;$candidate['exact_name']='Missing competitor';$candidate['current_category']='';$candidate['needs_restore']=false;$missing++;}
        }unset($candidate);
        return ['backup'=>$name,'total'=>count($candidates),'matched'=>$matched,'recoverable'=>$recoverable,'already_special'=>$alreadySpecial,'missing'=>$missing,'candidates'=>array_values($candidates)];
    }

    /** @return array{backup:string,candidates:int,restored:int,missing:int,safety_backup:string} */
    public static function restoreFromBackup(PDO $pdo,string $type,string $name,?int $userId):array
    {
        self::ensureSchema($pdo);$preview=self::previewBackup($pdo,$type,$name);if($preview['total']<1)throw new RuntimeException('The selected backup contains no Special Category assignments.');$safety=(new BackupService())->createDatabaseBackup($userId);
        $current=$pdo->prepare('SELECT current_division FROM bdc_competitor_discipline_profiles WHERE competitor_id=:competitor AND dance_style=:dance');$profile=$pdo->prepare("INSERT INTO bdc_competitor_discipline_profiles(competitor_id,dance_style,dance_role,current_division) VALUES(:competitor,:dance,:role,:category) ON DUPLICATE KEY UPDATE current_division=VALUES(current_division),updated_at=NOW()");$legacy=$pdo->prepare('UPDATE bdc_competitors SET current_division=:category WHERE id=:competitor');$record=$pdo->prepare("INSERT INTO bdc_special_category_recovery(audit_log_id,competitor_id,dance_style,recovered_category,audit_created_at,source_kind,source_name,before_category,applied_at) VALUES(NULL,:competitor,:dance,:category,NULL,'backup',:source,:before,NOW())");$restored=0;
        $pdo->beginTransaction();try{foreach($preview['candidates'] as $candidate){if((int)$candidate['competitor_id']<1||empty($candidate['needs_restore']))continue;$current->execute(['competitor'=>$candidate['competitor_id'],'dance'=>$candidate['dance_style']]);$before=(string)($current->fetchColumn()?:'unknown');$profile->execute(['competitor'=>$candidate['competitor_id'],'dance'=>$candidate['dance_style'],'role'=>in_array($candidate['dance_role'],['leader','follower','both','unknown'],true)?$candidate['dance_role']:'unknown','category'=>$candidate['category']]);if($candidate['dance_style']==='bachata')$legacy->execute(['category'=>$candidate['category'],'competitor'=>$candidate['competitor_id']]);$record->execute(['competitor'=>$candidate['competitor_id'],'dance'=>$candidate['dance_style'],'category'=>$candidate['category'],'source'=>$name,'before'=>$before]);$restored++;}$pdo->commit();}catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        return ['backup'=>$name,'candidates'=>$preview['total'],'restored'=>$restored,'missing'=>$preview['missing'],'safety_backup'=>$safety['name']];
    }

    private static function backupSql(string $type,string $name):string
    {
        $service=new BackupService();$path=$service->resolve($type,$name);$compressed='';
        if($type==='database')$compressed=(string)file_get_contents($path);else{if(!class_exists(ZipArchive::class))throw new RuntimeException('PHP ZipArchive is required to inspect a Full backup.');$zip=new ZipArchive();if($zip->open($path)!==true)throw new RuntimeException('Full backup could not be opened.');$entry='';for($i=0;$i<$zip->numFiles;$i++){ $candidate=(string)$zip->getNameIndex($i);if(str_starts_with($candidate,'database/')&&str_ends_with($candidate,'.sql.gz')){$entry=$candidate;break;}}if($entry===''){$zip->close();throw new RuntimeException('Full backup has no database snapshot.');}$compressed=(string)$zip->getFromName($entry);$zip->close();}
        $sql=gzdecode($compressed);if($sql===false||!str_contains($sql,'BDC Competitor Dashboard database backup'))throw new RuntimeException('Backup validation failed.');return$sql;
    }

    /** @return array<int,array<string,mixed>> */
    private static function tableRows(string $sql,string $table):array
    {
        $rows=[];$pattern='/INSERT INTO `'.preg_quote($table,'/').'` \((.*?)\) VALUES\n(.*?);\n/s';if(!preg_match_all($pattern,$sql,$batches,PREG_SET_ORDER))return[];
        foreach($batches as $batch){$columns=array_map(static fn(string $value):string=>trim($value," `\t\r\n"),explode(',',$batch[1]));foreach(self::tuples($batch[2]) as $values){if(count($values)!==count($columns))continue;$rows[]=array_combine($columns,$values);}}return$rows;
    }

    /** @return array<int,array<int,mixed>> */
    private static function tuples(string $values):array
    {
        $rows=[];$row=[];$field='';$depth=0;$quoted=false;$escaped=false;$length=strlen($values);
        for($i=0;$i<$length;$i++){$char=$values[$i];if($quoted){$field.=$char;if($escaped){$escaped=false;continue;}if($char==='\\'){$escaped=true;continue;}if($char==="'"&&($values[$i+1]??'')==="'"){$field.="'";$i++;continue;}if($char==="'")$quoted=false;continue;}if($char==="'"){$quoted=true;$field.=$char;continue;}if($char==='('){if($depth===0){$row=[];$field='';}$depth++;continue;}if($char===')'&&$depth===1){$row[]=self::value($field);$rows[]=$row;$depth=0;$field='';continue;}if($char===','&&$depth===1){$row[]=self::value($field);$field='';continue;}if($depth>0)$field.=$char;}
        return$rows;
    }

    private static function value(string $value):mixed
    {
        $value=trim($value);if(strtoupper($value)==='NULL')return null;if(strlen($value)>=2&&$value[0]==="'"&&$value[strlen($value)-1]==="'"){$value=substr($value,1,-1);$out='';$map=['0'=>"\0",'n'=>"\n",'r'=>"\r",'Z'=>chr(26),"'"=>"'",'"'=>'"','\\'=>'\\'];for($i=0,$length=strlen($value);$i<$length;$i++){if($value[$i]==='\\'&&$i+1<$length){$next=$value[++$i];$out.=$map[$next]??$next;}else$out.=$value[$i];}return str_replace("''","'",$out);}return$value;
    }
}
