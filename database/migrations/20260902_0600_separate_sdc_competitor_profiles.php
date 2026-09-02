<?php
declare(strict_types=1);

use PDO;

return static function(PDO $pdo):void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_sdc_competitors(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        competitor_id BIGINT UNSIGNED NOT NULL,
        sdc_id VARCHAR(32) NOT NULL,
        dance_role ENUM('leader','follower','both','unknown') NOT NULL DEFAULT 'unknown',
        current_division ENUM('unknown','novice','intermediate','advanced','all_star','professional') NOT NULL DEFAULT 'unknown',
        status ENUM('active','archived') NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_sdc_competitor(competitor_id),
        UNIQUE KEY uq_sdc_id(sdc_id),
        KEY idx_sdc_status_name(status,competitor_id),
        CONSTRAINT fk_sdc_shared_person FOREIGN KEY(competitor_id) REFERENCES bdc_competitors(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_sdc_competitor_categories(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        sdc_competitor_id BIGINT UNSIGNED NOT NULL,
        category ENUM('salsa_rising','salsa_open','salsa_invitational') NOT NULL,
        source_kind VARCHAR(40) NOT NULL DEFAULT 'migration',
        source_name VARCHAR(191) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_sdc_category(sdc_competitor_id,category),
        CONSTRAINT fk_sdc_category_profile FOREIGN KEY(sdc_competitor_id) REFERENCES bdc_sdc_competitors(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Backfill only existing SDC members. Shared person/photo/contact data remains
    // in bdc_competitors; no BDC result, point, event or WDC table is changed.
    $pdo->exec("INSERT INTO bdc_sdc_competitors(competitor_id,sdc_id,dance_role,current_division,status)
        SELECT ri.competitor_id,ri.identity_code,
               COALESCE(p.dance_role,'unknown'),
               CASE WHEN COALESCE(p.current_division,'unknown') IN ('intermediate','advanced') THEN p.current_division ELSE 'unknown' END,
               CASE WHEN c.status='archived' THEN 'archived' ELSE 'active' END
        FROM bdc_result_identities ri
        JOIN bdc_competitors c ON c.id=ri.competitor_id
        LEFT JOIN bdc_competitor_discipline_profiles p ON p.competitor_id=ri.competitor_id AND p.dance_style='salsa'
        WHERE ri.council='sdc'
        ON DUPLICATE KEY UPDATE sdc_id=VALUES(sdc_id),dance_role=VALUES(dance_role),current_division=VALUES(current_division),status=VALUES(status)");

    $pdo->exec("INSERT IGNORE INTO bdc_sdc_competitor_categories(sdc_competitor_id,category,source_kind,source_name)
        SELECT s.id,c.category,COALESCE(c.source_kind,'migration'),COALESCE(c.source_name,'SDC separation migration')
        FROM bdc_competitor_special_categories c
        JOIN bdc_sdc_competitors s ON s.competitor_id=c.competitor_id
        WHERE c.dance_style='salsa' AND c.category IN ('salsa_rising','salsa_open','salsa_invitational')");
};
