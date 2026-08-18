<?php
declare(strict_types=1);

use PDO;

return static function(PDO $pdo):void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_scoring_backups(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        round_id BIGINT UNSIGNED NOT NULL,
        data_mode ENUM('live','test') NOT NULL,
        backup_type ENUM('automatic','manual','pre_restore') NOT NULL DEFAULT 'automatic',
        action_name VARCHAR(100) NOT NULL,
        label VARCHAR(190) NULL,
        snapshot_hash CHAR(64) NOT NULL,
        snapshot_json LONGTEXT NOT NULL,
        summary_json LONGTEXT NULL,
        created_by BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        restored_by BIGINT UNSIGNED NULL,
        restored_at DATETIME NULL,
        restore_reason VARCHAR(500) NULL,
        INDEX idx_scoring_backup_round(round_id,data_mode,created_at),
        INDEX idx_scoring_backup_hash(snapshot_hash)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
