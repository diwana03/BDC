<?php
declare(strict_types=1);
return static function(PDO $pdo):void{
 $exists=$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bdc_special_category_legacy_quarantine'")->fetchColumn();
 if(!(int)$exists)return;
 $pdo->exec("INSERT IGNORE INTO bdc_competitor_special_categories(competitor_id,dance_style,category,source_kind,source_name,created_at,updated_at) SELECT competitor_id,dance_style,category,source_kind,source_name,COALESCE(original_created_at,quarantined_at),NOW() FROM bdc_special_category_legacy_quarantine WHERE quarantine_reason='Unsupported legacy profile value copied from pre-isolation Test activity'");
};
