<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use RuntimeException;

final class UnapprovedProfileRepairService
{
    /**
     * Profiles confirmed unsupported after comparing the named test-event
     * evidence with both original SBTA Open and Amateur Google Form exports.
     * This is intentionally an exact allowlist: never broaden it to inferred
     * event participants.
     */
    private static function confirmedUnsupportedProfiles():array
    {
        return [
            ['bdc_id'=>'BDC-000446','name'=>'ANDREA AVERSA','dance'=>'bachata','division'=>'bachata_rising'],
            ['bdc_id'=>'BDC-000208','name'=>'Aaron Then','dance'=>'salsa','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000205','name'=>'Abhi N','dance'=>'salsa','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000492','name'=>'Abhishek Khurana','dance'=>'salsa','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000315','name'=>'Aditya Ahluwalia','dance'=>'salsa','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000501','name'=>'Adrienn Marton','dance'=>'salsa','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000309','name'=>'Alethea Chua','dance'=>'salsa','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000355','name'=>'Alexandria Wong','dance'=>'salsa','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000398','name'=>'Angela Bok','dance'=>'salsa','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000295','name'=>'Angela Lim','dance'=>'salsa','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000468','name'=>'Anna Nguyen','dance'=>'salsa','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000216','name'=>'Antoine Michel','dance'=>'salsa','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000410','name'=>'Arsh','dance'=>'salsa','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000290','name'=>'Asef Purwanti','dance'=>'salsa','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000345','name'=>'Ashish Diwan','dance'=>'salsa','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000287','name'=>'Astrid Nicole','dance'=>'salsa','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000477','name'=>'Atlee Afroz','dance'=>'salsa','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000272','name'=>'Atsuko Hamada','dance'=>'salsa','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000236','name'=>'Aya Alimkulova','dance'=>'salsa','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000337','name'=>'Caius Chew','dance'=>'salsa','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000363','name'=>'Candice Le','dance'=>'salsa','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000391','name'=>'Cecilia Koh','dance'=>'salsa','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000214','name'=>'Celstine Chen','dance'=>'salsa','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000288','name'=>'Chitralekha Makhija','dance'=>'salsa','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000400','name'=>'Derrick Lye','dance'=>'salsa','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000245','name'=>'Jadyn Chua','dance'=>'salsa','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000438','name'=>'James Chan','dance'=>'salsa','division'=>'salsa_open'],
        ];
    }

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
        // Deployment migrations call with system user 0. Historical repair is
        // now preview-only because the pre-test baseline backup is unavailable.
        if($userId<1){$preview=self::diagnostic($pdo);return ['repaired'=>0,'skipped'=>count($preview['rows']),'safety_backup'=>'preview only'];}
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

    /** Apply only the source-sheet-verified correction approved on 26 Aug 2026. */
    public static function repairConfirmedUnsupportedProfiles(PDO $pdo,?int $userId=null):array
    {
        self::ensureSchema($pdo);
        $targets=self::confirmedUnsupportedProfiles();
        $lookup=$pdo->prepare("SELECT c.id,c.bdc_id,c.exact_name,p.dance_style,p.current_division,p.updated_at
          FROM bdc_competitors c
          JOIN bdc_competitor_discipline_profiles p ON p.competitor_id=c.id
          WHERE c.bdc_id=:bdc AND p.dance_style=:dance
          LIMIT 1");
        $supported=$pdo->prepare("SELECT
          (SELECT COUNT(*) FROM bdc_participant_results pr WHERE pr.competitor_id=:c1 AND pr.dance_style=:d1 AND (pr.source IN('historical_import','manual') OR EXISTS(SELECT 1 FROM bdc_scoring_publication_points spp JOIN bdc_scoring_publications sp ON sp.id=spp.publication_id AND sp.status='published' WHERE spp.participant_result_id=pr.id)))
          +(SELECT COUNT(*) FROM bdc_point_transactions pt WHERE pt.competitor_id=:c2 AND pt.dance_style=:d2 AND (pt.source_type IN('manual','csv_import','correction') OR EXISTS(SELECT 1 FROM bdc_scoring_publication_points spp JOIN bdc_scoring_publications sp ON sp.id=spp.publication_id AND sp.status='published' WHERE spp.point_transaction_id=pt.id)))");
        $eligible=[];$skipped=0;
        foreach($targets as $target){
            $lookup->execute(['bdc'=>$target['bdc_id'],'dance'=>$target['dance']]);$row=$lookup->fetch(PDO::FETCH_ASSOC);
            if(!$row || !hash_equals($target['division'],(string)$row['current_division']) || strcasecmp(trim($target['name']),trim((string)$row['exact_name']))!==0){$skipped++;continue;}
            $supported->execute(['c1'=>$row['id'],'d1'=>$target['dance'],'c2'=>$row['id'],'d2'=>$target['dance']]);
            if((int)$supported->fetchColumn()>0){$skipped++;continue;}
            $eligible[]=$row+$target;
        }
        if(!$eligible)return ['repaired'=>0,'skipped'=>$skipped,'safety_backup'=>'none'];
        $backup=(new BackupService())->createDatabaseBackup($userId);
        $delete=$pdo->prepare("DELETE FROM bdc_competitor_discipline_profiles WHERE competitor_id=:competitor AND dance_style=:dance AND current_division=:division");
        $clearLegacy=$pdo->prepare("UPDATE bdc_competitors SET current_division='unknown' WHERE id=:competitor AND current_division=:division");
        $history=$pdo->prepare("INSERT INTO bdc_unapproved_profile_repairs(competitor_id,dance_style,removed_division,evidence_json,repaired_by) VALUES(:competitor,:dance,:division,:evidence,:user)");
        $repaired=0;$pdo->beginTransaction();
        try{
            foreach($eligible as $row){
                // Recheck inside the transaction so a newly published/manual
                // record always wins over this one-time correction.
                $supported->execute(['c1'=>$row['id'],'d1'=>$row['dance'],'c2'=>$row['id'],'d2'=>$row['dance']]);
                if((int)$supported->fetchColumn()>0){$skipped++;continue;}
                $delete->execute(['competitor'=>$row['id'],'dance'=>$row['dance'],'division'=>$row['division']]);
                if($delete->rowCount()!==1){$skipped++;continue;}
                if($row['dance']==='bachata')$clearLegacy->execute(['competitor'=>$row['id'],'division'=>$row['division']]);
                $evidence=json_encode([
                    'bdc_id'=>$row['bdc_id'],'exact_name'=>$row['exact_name'],'dance_style'=>$row['dance'],
                    'removed_division'=>$row['division'],'profile_updated_at'=>$row['updated_at'],
                    'verification'=>'SBTA Open and Amateur source sheets checked 2026-08-26',
                    'published_or_manual_evidence'=>0,'recovery_history_retained'=>true,
                ],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
                if($evidence===false)throw new RuntimeException('Unable to encode confirmed repair evidence.');
                $history->execute(['competitor'=>$row['id'],'dance'=>$row['dance'],'division'=>$row['division'],'evidence'=>$evidence,'user'=>$userId]);
                $repaired++;
            }
            $pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        return ['repaired'=>$repaired,'skipped'=>$skipped,'safety_backup'=>(string)($backup['name']??'created')];
    }

    /** Non-destructive evidence report for only the named test events. */
    public static function diagnostic(PDO $pdo):array
    {
        self::ensureSchema($pdo);SpecialCategoryRecoveryService::ensureSchema($pdo);$targets=self::targetEventsSql();
        $sql="SELECT DISTINCT x.competitor_id,x.bdc_id,x.exact_name,x.dance_style,p.dance_role,p.current_division,p.updated_at
          FROM (
            SELECT c.id competitor_id,c.bdc_id,c.exact_name,r.dance_style
            FROM bdc_scoring_entries se JOIN bdc_scoring_rounds r ON r.id=se.round_id JOIN bdc_events e ON e.id=r.event_id JOIN bdc_competitors c ON c.id=se.competitor_id
            WHERE {$targets}
            UNION
            SELECT c.id,c.bdc_id,c.exact_name,r.dance_style
            FROM bdc_test_scoring_entries te JOIN bdc_test_scoring_rounds r ON r.id=te.round_id JOIN bdc_events e ON e.id=r.event_id JOIN bdc_test_competitors tc ON tc.id=te.competitor_id JOIN bdc_competitors c ON c.bdc_id=tc.bdc_id
            WHERE {$targets}
          ) x LEFT JOIN bdc_competitor_discipline_profiles p ON p.competitor_id=x.competitor_id AND p.dance_style=x.dance_style
          ORDER BY x.exact_name,x.dance_style";
        $rows=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $published=$pdo->prepare("SELECT COUNT(*) FROM bdc_scoring_publication_points spp JOIN bdc_scoring_publications sp ON sp.id=spp.publication_id AND sp.status='published' LEFT JOIN bdc_participant_results pr ON pr.id=spp.participant_result_id LEFT JOIN bdc_point_transactions pt ON pt.id=spp.point_transaction_id WHERE COALESCE(pr.competitor_id,pt.competitor_id)=:competitor AND COALESCE(pr.dance_style,pt.dance_style)=:dance");
        $manual=$pdo->prepare("SELECT (SELECT COUNT(*) FROM bdc_participant_results WHERE competitor_id=:c1 AND dance_style=:d1 AND source IN('historical_import','manual'))+(SELECT COUNT(*) FROM bdc_point_transactions WHERE competitor_id=:c2 AND dance_style=:d2 AND source_type IN('manual','csv_import','correction'))");
        $recovery=$pdo->prepare("SELECT COUNT(*) FROM bdc_special_category_recovery WHERE competitor_id=:competitor AND dance_style=:dance AND recovered_category=:division AND applied_at IS NOT NULL");
        foreach($rows as &$row){$published->execute(['competitor'=>$row['competitor_id'],'dance'=>$row['dance_style']]);$row['published_evidence']=(int)$published->fetchColumn();$manual->execute(['c1'=>$row['competitor_id'],'d1'=>$row['dance_style'],'c2'=>$row['competitor_id'],'d2'=>$row['dance_style']]);$row['manual_history']=(int)$manual->fetchColumn();$row['recovery_evidence']=0;if((string)($row['current_division']??'')!==''){$recovery->execute(['competitor'=>$row['competitor_id'],'dance'=>$row['dance_style'],'division'=>$row['current_division']]);$row['recovery_evidence']=(int)$recovery->fetchColumn();}$row['classification']=$row['current_division']===null?'no_profile':($row['published_evidence']>0?'published_protected':($row['manual_history']>0?'manual_history_protected':($row['recovery_evidence']>0?'recovery_evidence_review':'test_event_only_candidate')));}
        unset($row);return ['rows'=>$rows,'total'=>count($rows),'candidates'=>count(array_filter($rows,static fn(array $r):bool=>$r['classification']==='test_event_only_candidate'))];
    }
}
