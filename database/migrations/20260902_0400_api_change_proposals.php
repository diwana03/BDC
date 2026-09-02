<?php
declare(strict_types=1);

return static function(PDO $pdo):void{
    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_api_change_proposals (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        proposal_key VARCHAR(191) NOT NULL,
        source_system VARCHAR(80) NOT NULL,
        action_type VARCHAR(60) NOT NULL,
        target_id BIGINT UNSIGNED NOT NULL,
        payload_json LONGTEXT NOT NULL,
        before_json LONGTEXT NOT NULL,
        state_hash CHAR(64) NOT NULL,
        status ENUM('pending','approved','rejected','failed') NOT NULL DEFAULT 'pending',
        failure_message VARCHAR(500) NULL,
        submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reviewed_by BIGINT UNSIGNED NULL,
        reviewed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_api_change_proposal_key (proposal_key),
        KEY idx_api_change_status (status,submitted_at),
        KEY idx_api_change_target (action_type,target_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
