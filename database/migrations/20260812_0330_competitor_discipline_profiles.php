<?php
declare(strict_types=1);
return static function(PDO $pdo):void{
    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_competitor_discipline_profiles(
        competitor_id BIGINT UNSIGNED NOT NULL,
        dance_style ENUM('bachata','salsa') NOT NULL,
        dance_role ENUM('leader','follower','both','unknown') NOT NULL DEFAULT 'unknown',
        current_division ENUM('novice','intermediate','advanced','all_star','professional','unknown') NOT NULL DEFAULT 'unknown',
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY(competitor_id,dance_style),
        CONSTRAINT fk_competitor_discipline_competitor FOREIGN KEY(competitor_id) REFERENCES bdc_competitors(id) ON DELETE CASCADE,
        INDEX idx_competitor_discipline_style(dance_style,current_division,dance_role)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("INSERT INTO bdc_competitor_discipline_profiles(competitor_id,dance_style,dance_role,current_division)
        SELECT id,'bachata',dance_role,current_division FROM bdc_competitors
        ON DUPLICATE KEY UPDATE dance_role=VALUES(dance_role),current_division=VALUES(current_division)");
};