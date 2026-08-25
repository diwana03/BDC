<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use RuntimeException;

final class UnapprovedProfileRepairService
{
    public static function ensureSchema(PDO $pdo):void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_unapproved_profile_repairs(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            competitor_id BIGINT UNSIGNED NOT NULL,
            dance_style ENUM('bachata','salsa') NOT NULL,
            removed_division VARCHAR(40) NOT NULL,
            evidence_json LONGTEXT NOT NULL,
            repaired_by BIGINT UNSIGNED NULL,
            repaired_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_unapproved_profile_competitor(competitor_id,dance_style)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /** Salsa profiles with event/Test evidence but no approved Salsa ledger. */
    public static function preview(PDO $pdo):array
    {
        self::ensureSchema($pdo);
        SpecialCategoryRecoveryService::ensureSchema($pdo);
        $sql="SELECT p.competitor_id,c.bdc_id,c.exact_name,p.dance_role,p.current_division,p.updated_at,
          (SELECT COUNT(*) FROM bdc_scoring_entries se JOIN bdc_scoring_rounds sr ON sr.id=se.round_id WHERE se.competitor_id=p.competitor_id AND sr.dance_style='salsa') live_entries,
          (SELECT COUNT(*) FROM bdc_test_scoring_entries te JOIN bdc_test_scoring_rounds tr ON tr.id=te.round_id JOIN bdc_test_competitors tc ON tc.id=te.competitor_id WHERE tr.dance_style='salsa' AND (tc.id=p.competitor_id OR tc.bdc_id=c.bdc_id)) test_entries
         FROM bdc_competitor_discipline_profiles p
         JOIN bdc_competitors c ON c.id=p.competitor_id
         WHERE p.dance_style='salsa'
           AND p.current_division IN('novice','intermediate','advanced','semi_pro','pro','professional','all_star','unknown','salsa_rising','salsa_open')
           AND NOT EXISTS(SELECT 1 FROM bdc_participant_results pr WHERE pr.competitor_id=p.competitor_id AND pr.dance_style='salsa')
           AND NOT EXISTS(SELECT 1 FROM bdc_point_transactions pt WHERE pt.competitor_id=p.competitor_id AND pt.dance_style='salsa')
           AND (p.current_division NOT IN('salsa_rising','salsa_open') OR NOT EXISTS(
             SELECT 1 FROM bdc_special_category_recovery scr
             WHERE scr.competitor_id=p.competitor_id AND scr.dance_style='salsa'
               AND scr.recovered_category=p.current_division AND scr.applied_at IS NOT NULL
           ))
           AND (EXISTS(SELECT 1 FROM bdc_scoring_entries se JOIN bdc_scoring_rounds sr ON sr.id=se.round_id WHERE se.competitor_id=p.competitor_id AND sr.dance_style='salsa')
             OR EXISTS(SELECT 1 FROM bdc_test_scoring_entries te JOIN bdc_test_scoring_rounds tr ON tr.id=te.round_id JOIN bdc_test_competitors tc ON tc.id=te.competitor_id WHERE tr.dance_style='salsa' AND (tc.id=p.competitor_id OR tc.bdc_id=c.bdc_id)))
         ORDER BY c.exact_name,c.id";
        $rows=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        return ['candidates'=>count($rows),'rows'=>$rows];
    }

    public static function repair(PDO $pdo,int $userId):array
    {
        $preview=self::preview($pdo);if(!$preview['rows'])return ['repaired'=>0,'skipped'=>0,'safety_backup'=>'none'];
        $backup=(new BackupService())->createDatabaseBackup($userId);
        $history=$pdo->prepare("INSERT INTO bdc_unapproved_profile_repairs(competitor_id,dance_style,removed_division,evidence_json,repaired_by) VALUES(:competitor,'salsa',:division,:evidence,:user)");
        $approved=$pdo->prepare("SELECT (SELECT COUNT(*) FROM bdc_participant_results WHERE competitor_id=:c1 AND dance_style='salsa')+(SELECT COUNT(*) FROM bdc_point_transactions WHERE competitor_id=:c2 AND dance_style='salsa')");
        $specialEvidence=$pdo->prepare("SELECT COUNT(*) FROM bdc_special_category_recovery WHERE competitor_id=:competitor AND dance_style='salsa' AND recovered_category=:division AND applied_at IS NOT NULL");
        $delete=$pdo->prepare("DELETE FROM bdc_competitor_discipline_profiles WHERE competitor_id=:competitor AND dance_style='salsa' AND current_division=:division");
        $repaired=0;$skipped=0;$pdo->beginTransaction();
        try{
            foreach($preview['rows'] as $row){
                $approved->execute(['c1'=>$row['competitor_id'],'c2'=>$row['competitor_id']]);if((int)$approved->fetchColumn()>0){$skipped++;continue;}
                if(in_array($row['current_division'],['salsa_rising','salsa_open'],true)){$specialEvidence->execute(['competitor'=>$row['competitor_id'],'division'=>$row['current_division']]);if((int)$specialEvidence->fetchColumn()>0){$skipped++;continue;}}
                $evidence=json_encode(['bdc_id'=>$row['bdc_id'],'live_entries'=>(int)$row['live_entries'],'test_entries'=>(int)$row['test_entries'],'profile_updated_at'=>$row['updated_at'],'special_category_protection_checked'=>in_array($row['current_division'],['salsa_rising','salsa_open'],true)],JSON_UNESCAPED_SLASHES);
                $delete->execute(['competitor'=>$row['competitor_id'],'division'=>$row['current_division']]);if($delete->rowCount()!==1){$skipped++;continue;}
                $history->execute(['competitor'=>$row['competitor_id'],'division'=>$row['current_division'],'evidence'=>$evidence,'user'=>$userId?:null]);$repaired++;
            }
            $pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        return ['repaired'=>$repaired,'skipped'=>$skipped,'safety_backup'=>(string)($backup['name']??'created')];
    }
}
