<?php
declare(strict_types=1);

return static function(PDO $pdo):void{
    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_form_sync_submissions(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        source_system VARCHAR(80) NOT NULL,
        source_key VARCHAR(191) NOT NULL,
        payload_hash CHAR(64) NOT NULL,
        form_kind ENUM('open','amateur') NOT NULL,
        source_row INT UNSIGNED NULL,
        participant_name VARCHAR(190) NOT NULL,
        competitor_id BIGINT UNSIGNED NULL,
        candidate_ids_json TEXT NULL,
        profile_requests_json TEXT NULL,
        status ENUM('processing','completed','duplicate','pending_review','failed') NOT NULL DEFAULT 'processing',
        error_message TEXT NULL,
        payload_json LONGTEXT NOT NULL,
        processed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE INDEX uq_form_sync_source(source_system,source_key),
        UNIQUE INDEX uq_form_sync_payload(source_system,payload_hash),
        INDEX idx_form_sync_status(status,created_at),
        INDEX idx_form_sync_competitor(competitor_id),
        CONSTRAINT fk_form_sync_competitor FOREIGN KEY(competitor_id) REFERENCES bdc_competitors(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
