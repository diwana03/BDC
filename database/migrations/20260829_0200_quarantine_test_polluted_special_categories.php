<?php
declare(strict_types=1);
return static function(PDO $pdo):void{
 $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_special_category_legacy_quarantine(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,original_category_id BIGINT UNSIGNED NOT NULL,competitor_id BIGINT UNSIGNED NOT NULL,dance_style VARCHAR(20) NOT NULL,category VARCHAR(40) NOT NULL,source_kind VARCHAR(30) NOT NULL,source_name VARCHAR(255) NULL,original_created_at DATETIME NULL,quarantine_reason VARCHAR(255) NOT NULL,quarantined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_legacy_category_quarantine(original_category_id),INDEX idx_legacy_category_quarantine_competitor(competitor_id,dance_style,category)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 /* Preserve every manual, data-entry, recovery and approved result category. */
 $pdo->exec("INSERT IGNORE INTO bdc_special_category_legacy_quarantine(original_category_id,competitor_id,dance_style,category,source_kind,source_name,original_created_at,quarantine_reason)
 SELECT sc.id,sc.competitor_id,sc.dance_style,sc.category,sc.source_kind,sc.source_name,sc.created_at,'Unsupported legacy profile value copied from pre-isolation Test activity'
 FROM bdc_competitor_special_categories sc
 WHERE sc.source_kind='legacy_profile'
 AND NOT EXISTS(SELECT 1 FROM bdc_special_category_recovery recovery WHERE recovery.competitor_id=sc.competitor_id AND recovery.dance_style=sc.dance_style AND recovery.recovered_category=sc.category AND recovery.applied_at IS NOT NULL)
 AND NOT EXISTS(SELECT 1 FROM bdc_scoring_publication_points publication_point JOIN bdc_scoring_publications publication ON publication.id=publication_point.publication_id JOIN bdc_participant_results participant_result ON participant_result.id=publication_point.participant_result_id WHERE publication_point.competitor_id=sc.competitor_id AND publication.status='published' AND publication.approved_by IS NOT NULL AND publication.division=sc.category AND participant_result.competitor_id=sc.competitor_id AND participant_result.dance_style=sc.dance_style)");
 $pdo->exec("DELETE sc FROM bdc_competitor_special_categories sc JOIN bdc_special_category_legacy_quarantine quarantine ON quarantine.original_category_id=sc.id WHERE sc.source_kind='legacy_profile'");
};
