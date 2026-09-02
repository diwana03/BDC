<?php
declare(strict_types=1);

return static function(PDO $pdo):void{
    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_bdc_identity_detachment_archive(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        competitor_id BIGINT UNSIGNED NOT NULL,
        bdc_id VARCHAR(32) NULL,
        identity_json LONGTEXT NOT NULL,
        wdc_json LONGTEXT NOT NULL,
        approved_by BIGINT UNSIGNED NULL,
        detached_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_bdc_identity_detachment_competitor(competitor_id),
        KEY idx_bdc_identity_detachment_code(bdc_id),
        CONSTRAINT fk_bdc_identity_detachment_person FOREIGN KEY(competitor_id) REFERENCES bdc_competitors(id) ON DELETE RESTRICT,
        CONSTRAINT fk_bdc_identity_detachment_user FOREIGN KEY(approved_by) REFERENCES bdc_users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
