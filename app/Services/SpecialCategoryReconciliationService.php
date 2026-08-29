<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class SpecialCategoryReconciliationService
{
    private const PROTECTED_SOURCES=['manual','data_entry','audit','backup','recovery'];

    /** @return array{rows:array<int,array<string,mixed>>,keep:int,quarantine:int,review:int,evidence_available:bool} */
    public static function preview(PDO $pdo):array
    {
        SpecialCategoryRecoveryService::ensureSchema($pdo);
        $evidenceAvailable=true;
        try {
            $sql="SELECT sc.*,c.bdc_id,c.exact_name,
                EXISTS(SELECT 1 FROM bdc_special_category_recovery r WHERE r.competitor_id=sc.competitor_id AND r.dance_style=sc.dance_style AND r.recovered_category=sc.category AND r.applied_at IS NOT NULL) recovery_evidence,
                EXISTS(SELECT 1 FROM bdc_scoring_publication_points pp JOIN bdc_scoring_publications p ON p.id=pp.publication_id JOIN bdc_participant_results pr ON pr.id=pp.participant_result_id WHERE pp.competitor_id=sc.competitor_id AND p.status='published' AND p.approved_by IS NOT NULL AND p.division=sc.category AND pr.competitor_id=sc.competitor_id AND pr.dance_style=sc.dance_style) approved_evidence
                FROM bdc_competitor_special_categories sc JOIN bdc_competitors c ON c.id=sc.competitor_id ORDER BY c.exact_name,sc.dance_style,sc.category,sc.id";
            $rows=$pdo->query($sql)->fetchAll();
        } catch (\Throwable) {
            $evidenceAvailable=false;
            $rows=$pdo->query("SELECT sc.*,c.bdc_id,c.exact_name,0 recovery_evidence,0 approved_evidence FROM bdc_competitor_special_categories sc JOIN bdc_competitors c ON c.id=sc.competitor_id ORDER BY c.exact_name,sc.dance_style,sc.category,sc.id")->fetchAll();
        }
        $counts=['keep'=>0,'quarantine'=>0,'review'=>0];
        foreach($rows as &$row){
            $source=strtolower(trim((string)$row['source_kind']));$recovery=(bool)$row['recovery_evidence'];$approved=(bool)$row['approved_evidence'];
            if(in_array($source,self::PROTECTED_SOURCES,true)){$classification='keep';$reason='Protected '.str_replace('_',' ',$source).' assignment';}
            elseif($recovery){$classification='keep';$reason='Verified recovery evidence';}
            elseif($approved){$classification='keep';$reason='Published and Super Admin approved result';}
            elseif(!$evidenceAvailable){$classification='review';$reason='Approval evidence could not be checked safely';}
            elseif($source==='legacy_profile'){$classification='quarantine';$reason='Legacy profile value with no manual, registration, recovery or approved-result evidence';}
            else{$classification='review';$reason='Unknown source requires Super Admin review';}
            $row['classification']=$classification;$row['reason']=$reason;$counts[$classification]++;
        }unset($row);
        return ['rows'=>$rows,'evidence_available'=>$evidenceAvailable]+$counts;
    }

    /** @param array<int,int> $ids */
    public static function quarantine(PDO $pdo,array $ids,?int $userId):array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$ids),static fn(int $id):bool=>$id>0)));if($ids===[])throw new RuntimeException('Select at least one Quarantine candidate.');
        $preview=self::preview($pdo);$allowed=[];foreach($preview['rows'] as $row)if($row['classification']==='quarantine')$allowed[(int)$row['id']]=$row;
        $selected=[];foreach($ids as $id){if(!isset($allowed[$id]))throw new RuntimeException('Selection contains a protected or unverified assignment. Refresh the preview.');$selected[]=$allowed[$id];}
        $backup=(new BackupService())->createDatabaseBackup($userId);
        $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_special_category_legacy_quarantine(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,original_category_id BIGINT UNSIGNED NOT NULL,competitor_id BIGINT UNSIGNED NOT NULL,dance_style VARCHAR(20) NOT NULL,category VARCHAR(40) NOT NULL,source_kind VARCHAR(30) NOT NULL,source_name VARCHAR(255) NULL,original_created_at DATETIME NULL,quarantine_reason VARCHAR(255) NOT NULL,quarantined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_legacy_category_quarantine(original_category_id),INDEX idx_legacy_category_quarantine_competitor(competitor_id,dance_style,category)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $store=$pdo->prepare("INSERT INTO bdc_special_category_legacy_quarantine(original_category_id,competitor_id,dance_style,category,source_kind,source_name,original_created_at,quarantine_reason) VALUES(:original,:competitor,:dance,:category,:kind,:source,:created,:reason) ON DUPLICATE KEY UPDATE quarantine_reason=VALUES(quarantine_reason),quarantined_at=NOW()");
        $delete=$pdo->prepare("DELETE FROM bdc_competitor_special_categories WHERE id=:id AND source_kind='legacy_profile'");$removed=0;
        $pdo->beginTransaction();try{foreach($selected as $row){$store->execute(['original'=>$row['id'],'competitor'=>$row['competitor_id'],'dance'=>$row['dance_style'],'category'=>$row['category'],'kind'=>$row['source_kind'],'source'=>$row['source_name'],'created'=>$row['created_at'],'reason'=>$row['reason']]);$delete->execute(['id'=>$row['id']]);$removed+=$delete->rowCount();}$pdo->commit();}catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
        return ['selected'=>count($selected),'quarantined'=>$removed,'safety_backup'=>$backup['name'],'ids'=>$ids];
    }
}
