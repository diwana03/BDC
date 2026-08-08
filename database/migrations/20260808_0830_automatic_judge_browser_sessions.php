<?php
declare(strict_types=1);

use PDO;

return static function(PDO $pdo):void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_scoring_judge_sessions(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        round_id BIGINT UNSIGNED NOT NULL,
        judge_id BIGINT UNSIGNED NOT NULL,
        token_hash CHAR(64) NOT NULL,
        token_hint VARCHAR(16) NOT NULL,
        status VARCHAR(24) NOT NULL DEFAULT 'not_started',
        opened_at DATETIME NULL,
        last_saved_at DATETIME NULL,
        submitted_at DATETIME NULL,
        unlocked_at DATETIME NULL,
        unlocked_by BIGINT UNSIGNED NULL,
        unlock_reason VARCHAR(500) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE INDEX uq_judge_browser_judge(judge_id),
        UNIQUE INDEX uq_judge_browser_token(token_hash),
        INDEX idx_judge_browser_round(round_id,status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
