<?php
declare(strict_types=1);

return static function(PDO $pdo):void{
    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_profile_integration_batches(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        batch_key VARCHAR(191) NOT NULL,
        source_system VARCHAR(80) NOT NULL,
        status ENUM('receiving','pending_review','partially_reviewed','completed','rejected') NOT NULL DEFAULT 'receiving',
        submitted_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE INDEX uq_profile_integration_batch(batch_key),
        INDEX idx_profile_integration_batch_status(status,created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_profile_integration_updates(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        batch_id BIGINT UNSIGNED NOT NULL,
        entity_type ENUM('competitor','judge') NOT NULL,
        source_key VARCHAR(191) NOT NULL,
        source_fingerprint CHAR(64) NOT NULL,
        payload_hash CHAR(64) NOT NULL,
        target_id BIGINT UNSIGNED NULL,
        match_status ENUM('new','matched','ambiguous','invalid') NOT NULL,
        candidate_ids_json TEXT NULL,
        payload_json LONGTEXT NOT NULL,
        staged_photo_name VARCHAR(255) NULL,
        staged_photo_mime VARCHAR(80) NULL,
        staged_photo_hash CHAR(64) NULL,
        status ENUM('pending','approved','rejected','failed') NOT NULL DEFAULT 'pending',
        error_message TEXT NULL,
        reviewed_by BIGINT UNSIGNED NULL,
        reviewed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE INDEX uq_profile_integration_source(source_fingerprint),
        INDEX idx_profile_integration_review(status,entity_type,created_at),
        INDEX idx_profile_integration_batch(batch_id,status),
        CONSTRAINT fk_profile_integration_batch FOREIGN KEY(batch_id) REFERENCES bdc_profile_integration_batches(id) ON DELETE CASCADE,
        CONSTRAINT fk_profile_integration_reviewer FOREIGN KEY(reviewed_by) REFERENCES bdc_users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
