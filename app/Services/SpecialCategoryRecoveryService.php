<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

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
        $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_special_category_recovery(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,audit_log_id BIGINT UNSIGNED NOT NULL,competitor_id BIGINT UNSIGNED NOT NULL,dance_style ENUM('bachata','salsa') NOT NULL,recovered_category VARCHAR(40) NOT NULL,audit_created_at DATETIME NOT NULL,applied_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_special_recovery_audit(audit_log_id),INDEX idx_special_recovery_competitor(competitor_id,dance_style)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}
