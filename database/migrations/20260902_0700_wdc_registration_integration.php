<?php
declare(strict_types=1);

return static function(PDO $pdo):void{
    $pdo->exec("ALTER TABLE bdc_profile_integration_updates MODIFY entity_type ENUM('competitor','judge','wdc_identity') NOT NULL");
    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_wdc_registrations(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        wdc_identity_id BIGINT UNSIGNED NOT NULL,
        event_key VARCHAR(191) NOT NULL,
        event_name VARCHAR(190) NOT NULL,
        category_key VARCHAR(120) NOT NULL,
        category_name VARCHAR(190) NOT NULL,
        dance_style ENUM('bachata','salsa','other') NOT NULL,
        entry_type ENUM('solo','couple','duo','pro_am','team') NOT NULL,
        competition_level VARCHAR(30) NOT NULL DEFAULT 'open',
        source_system VARCHAR(80) NOT NULL,
        source_key VARCHAR(191) NOT NULL,
        status ENUM('registered','withdrawn') NOT NULL DEFAULT 'registered',
        approved_by BIGINT UNSIGNED NULL,
        approved_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_wdc_registration(event_key,wdc_identity_id,category_key),
        INDEX idx_wdc_registration_category(event_key,category_key,status),
        INDEX idx_wdc_registration_identity(wdc_identity_id,status),
        CONSTRAINT fk_wdc_registration_identity FOREIGN KEY(wdc_identity_id) REFERENCES bdc_wdc_identities(id) ON DELETE RESTRICT,
        CONSTRAINT fk_wdc_registration_approver FOREIGN KEY(approved_by) REFERENCES bdc_users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
