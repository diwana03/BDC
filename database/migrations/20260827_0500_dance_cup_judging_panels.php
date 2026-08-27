<?php
declare(strict_types=1);

return static function(PDO $pdo):void{
    foreach(['bdc_dance_cup','bdc_test_dance_cup'] as $p){
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}_judging_panels(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id BIGINT UNSIGNED NOT NULL,
            panel_name VARCHAR(190) NOT NULL,
            discipline VARCHAR(80) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_dc_panel_name(event_id,panel_name),
            INDEX idx_dc_panel_event(event_id,status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}_judging_panel_categories(
            panel_id BIGINT UNSIGNED NOT NULL,
            competition_id BIGINT UNSIGNED NOT NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 1,
            PRIMARY KEY(panel_id,competition_id),
            UNIQUE KEY uq_dc_panel_category(competition_id),
            INDEX idx_dc_panel_category_order(panel_id,sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$p}_judging_panel_judges(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            panel_id BIGINT UNSIGNED NOT NULL,
            judge_id BIGINT UNSIGNED NULL,
            judge_name VARCHAR(190) NOT NULL,
            judge_order INT UNSIGNED NOT NULL DEFAULT 1,
            is_chief TINYINT(1) NOT NULL DEFAULT 0,
            access_token CHAR(64) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_dc_panel_judge_name(panel_id,judge_name),
            UNIQUE KEY uq_dc_panel_judge_token(access_token),
            INDEX idx_dc_panel_judge_order(panel_id,is_chief,judge_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
};
