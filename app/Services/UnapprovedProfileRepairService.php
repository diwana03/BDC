<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use RuntimeException;

final class UnapprovedProfileRepairService
{
    private static function targetEventsSql():string
    {
        return "(e.name LIKE 'BDC LIVE PARITY TEST - DO NOT PUBLISH%'
          OR e.name IN(
            '4th ASIA Open SALSA JACK & JILL COMPETITION 2026',
            'TEST EVENT 2 - Michael''s Imaginary J&J Event',
            '1st Asia Amateur Salsa Jack & Jill Competition',
            '4th ASIA Open BACHATA JACK & JILL COMPETITION 2026',
            '1st Asia Amateur Bachata Jack & Jill Competition',
            'SBTA Bachata Rising',
            'BASS × Timba Tropical Collaboration'
          ))";
    }

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

    /** Discipline profiles with event/Test evidence but no published ledger. */
    public static function preview(PDO $pdo):array
    {
        self::ensureSchema($pdo);
        SpecialCategoryRecoveryService::ensureSchema($pdo);
        $targets=self::targetEventsSql();
        $sql="SELECT p.competitor_id,c.bdc_id,c.exact_name,p.dance_style,p.dance_role,p.current_division,p.updated_at,
          (SELECT COUNT(*) FROM bdc_scoring_entries se JOIN bdc_scoring_rounds sr ON sr.id=se.round_id JOIN bdc_events e ON e.id=sr.event_id WHERE se.competitor_id=p.competitor_id AND sr.dance_style=p.dance_style AND {$targets}) live_entries,
          (SELECT COUNT(*) FROM bdc_test_scoring_entries te JOIN bdc_test_scoring_rounds tr ON tr.id=te.round_id JOIN bdc_events e ON e.id=tr.event_id JOIN bdc_test_competitors tc ON tc.id=te.competitor_id WHERE tr.dance_style=p.dance_style AND (tc.id=p.competitor_id OR tc.bdc_id=c.bdc_id) AND {$targets}) test_entries
         FROM bdc_competitor_discipline_profiles p
         JOIN bdc_competitors c ON c.id=p.competitor_id
         WHERE p.dance_style IN('bachata','salsa')
           AND p.current_division IN('novice','intermediate','advanced','semi_pro','pro','professional','all_star','unknown','bachata_rising','bachata_open','bachata_invitational','salsa_rising','salsa_open')
           AND NOT EXISTS(
             SELECT 1 FROM bdc_participant_results pr
             WHERE pr.competitor_id=p.competitor_id AND pr.dance_style=p.dance_style
               AND (pr.source IN('historical_import','manual') OR EXISTS(
                 SELECT 1 FROM bdc_scoring_publication_points spp
                 JOIN bdc_scoring_publications sp ON sp.id=spp.publication_id AND sp.status='published'
                 WHERE spp.participant_result_id=pr.id
               ))
           )
           AND NOT EXISTS(
             SELECT 1 FROM bdc_point_transactions pt
             WHERE pt.competitor_id=p.competitor_id AND pt.dance_style=p.dance_style
               AND (pt.source_type IN('manual','csv_import','correction') OR EXISTS(
                 SELECT 1 FROM bdc_scoring_publication_points spp
                 JOIN bdc_scoring_publications sp ON sp.id=spp.publication_id AND sp.status='published'
                 WHERE spp.point_transaction_id=pt.id
               ))
           )
           AND (p.current_division NOT IN('bachata_rising','bachata_open','bachata_invitational','salsa_rising','salsa_open') OR NOT EXISTS(
             SELECT 1 FROM bdc_special_category_recovery scr
             WHERE scr.competitor_id=p.competitor_id AND scr.dance_style=p.dance_style
               AND scr.recovered_category=p.current_division AND scr.applied_at IS NOT NULL
           ))
           AND (EXISTS(SELECT 1 FROM bdc_scoring_entries se JOIN bdc_scoring_rounds sr ON sr.id=se.round_id JOIN bdc_events e ON e.id=sr.event_id WHERE se.competitor_id=p.competitor_id AND sr.dance_style=p.dance_style AND {$targets})
             OR EXISTS(SELECT 1 FROM bdc_test_scoring_entries te JOIN bdc_test_scoring_rounds tr ON tr.id=te.round_id JOIN bdc_events e ON e.id=tr.event_id JOIN bdc_test_competitors tc ON tc.id=te.competitor_id WHERE tr.dance_style=p.dance_style AND (tc.id=p.competitor_id OR tc.bdc_id=c.bdc_id) AND {$targets}))
         ORDER BY c.exact_name,c.id,p.dance_style";
        $rows=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        return ['candidates'=>count($rows),'rows'=>$rows];
    }

    public static function repair(PDO $pdo,int $userId):array
    {
        $preview=self::preview($pdo);if(!$preview['rows'])return ['repaired'=>0,'skipped'=>0,'safety_backup'=>'none'];
        $backup=(new BackupService())->createDatabaseBackup($userId);
        $history=$pdo->prepare("INSERT INTO bdc_unapproved_profile_repairs(competitor_id,dance_style,removed_division,evidence_json,repaired_by) VALUES(:competitor,:dance,:division,:evidence,:user)");
        $approved=$pdo->prepare("SELECT
          (SELECT COUNT(*) FROM bdc_participant_results pr WHERE pr.competitor_id=:c1 AND pr.dance_style=:dance1 AND (pr.source IN('historical_import','manual') OR EXISTS(SELECT 1 FROM bdc_scoring_publication_points spp JOIN bdc_scoring_publications sp ON sp.id=spp.publication_id AND sp.status='published' WHERE spp.participant_result_id=pr.id)))
          +(SELECT COUNT(*) FROM bdc_point_transactions pt WHERE pt.competitor_id=:c2 AND pt.dance_style=:dance2 AND (pt.source_type IN('manual','csv_import','correction') OR EXISTS(SELECT 1 FROM bdc_scoring_publication_points spp JOIN bdc_scoring_publications sp ON sp.id=spp.publication_id AND sp.status='published' WHERE spp.point_transaction_id=pt.id)))");
        $specialEvidence=$pdo->prepare("SELECT COUNT(*) FROM bdc_special_category_recovery WHERE competitor_id=:competitor AND dance_style=:dance AND recovered_category=:division AND applied_at IS NOT NULL");
        $targetEvidence=$pdo->prepare("SELECT
          (SELECT COUNT(*) FROM bdc_scoring_entries se JOIN bdc_scoring_rounds sr ON sr.id=se.round_id JOIN bdc_events e ON e.id=sr.event_id WHERE se.competitor_id=:competitor1 AND sr.dance_style=:dance1 AND ".self::targetEventsSql().")
          +(SELECT COUNT(*) FROM bdc_test_scoring_entries te JOIN bdc_test_scoring_rounds tr ON tr.id=te.round_id JOIN bdc_events e ON e.id=tr.event_id JOIN bdc_test_competitors tc ON tc.id=te.competitor_id WHERE tc.bdc_id=:bdc AND tr.dance_style=:dance2 AND ".self::targetEventsSql().")");
        $delete=$pdo->prepare("DELETE FROM bdc_competitor_discipline_profiles WHERE competitor_id=:competitor AND dance_style=:dance AND current_division=:division");
        $clearLegacy=$pdo->prepare("UPDATE bdc_competitors SET current_division='unknown' WHERE id=:competitor AND current_division=:division");
        $repaired=0;$skipped=0;$pdo->beginTransaction();
        try{
            foreach($preview['rows'] as $row){
                $dance=(string)$row['dance_style'];$special=in_array($row['current_division'],['bachata_rising','bachata_open','bachata_invitational','salsa_rising','salsa_open'],true);
                $targetEvidence->execute(['competitor1'=>$row['competitor_id'],'dance1'=>$dance,'bdc'=>$row['bdc_id'],'dance2'=>$dance]);if((int)$targetEvidence->fetchColumn()<1){$skipped++;continue;}
                $approved->execute(['c1'=>$row['competitor_id'],'dance1'=>$dance,'c2'=>$row['competitor_id'],'dance2'=>$dance]);if((int)$approved->fetchColumn()>0){$skipped++;continue;}
                if($special){$specialEvidence->execute(['competitor'=>$row['competitor_id'],'dance'=>$dance,'division'=>$row['current_division']]);if((int)$specialEvidence->fetchColumn()>0){$skipped++;continue;}}
                $evidence=json_encode(['bdc_id'=>$row['bdc_id'],'dance_style'=>$dance,'live_entries'=>(int)$row['live_entries'],'test_entries'=>(int)$row['test_entries'],'profile_updated_at'=>$row['updated_at'],'special_category_protection_checked'=>$special],JSON_UNESCAPED_SLASHES);
                $delete->execute(['competitor'=>$row['competitor_id'],'dance'=>$dance,'division'=>$row['current_division']]);if($delete->rowCount()!==1){$skipped++;continue;}
                if($dance==='bachata')$clearLegacy->execute(['competitor'=>$row['competitor_id'],'division'=>$row['current_division']]);
                $history->execute(['competitor'=>$row['competitor_id'],'dance'=>$dance,'division'=>$row['current_division'],'evidence'=>$evidence,'user'=>$userId?:null]);$repaired++;
            }
            $pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        return ['repaired'=>$repaired,'skipped'=>$skipped,'safety_backup'=>(string)($backup['name']??'created')];
    }
}
