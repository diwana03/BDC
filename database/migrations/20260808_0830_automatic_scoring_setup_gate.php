<?php
declare(strict_types=1);

use App\Core\Database;

return static function():void{
    $pdo=Database::connection();
    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_scoring_round_setup (
        round_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
        confirmed_at DATETIME NULL,
        confirmed_by BIGINT UNSIGNED NULL,
        confirmed_snapshot_hash CHAR(64) NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_bdc_scoring_round_setup_round FOREIGN KEY (round_id) REFERENCES bdc_scoring_rounds(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
