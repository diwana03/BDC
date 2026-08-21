<?php
declare(strict_types=1);

use App\Core\Database;

return static function():void{
    Database::connection()->exec("CREATE TABLE IF NOT EXISTS bdc_scoring_roster_checkpoints(
        data_mode ENUM('real','test') NOT NULL,
        round_id BIGINT UNSIGNED NOT NULL,
        status ENUM('draft','submitted') NOT NULL DEFAULT 'draft',
        snapshot_hash CHAR(64) NULL,
        saved_at DATETIME NULL,
        saved_by BIGINT UNSIGNED NULL,
        submitted_at DATETIME NULL,
        submitted_by BIGINT UNSIGNED NULL,
        reopened_at DATETIME NULL,
        reopened_by BIGINT UNSIGNED NULL,
        reopen_reason VARCHAR(500) NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY(data_mode,round_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
