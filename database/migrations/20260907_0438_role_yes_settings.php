<?php
declare(strict_types=1);

return static function(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_scoring_role_yes_settings (
        round_id BIGINT UNSIGNED NOT NULL,
        dance_role VARCHAR(16) NOT NULL,
        yes_count INT UNSIGNED NOT NULL,
        locked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        locked_by BIGINT UNSIGNED NULL,
        PRIMARY KEY(round_id,dance_role),
        CONSTRAINT fk_bdc_role_yes_round FOREIGN KEY(round_id) REFERENCES bdc_scoring_rounds(id) ON DELETE CASCADE,
        INDEX idx_bdc_role_yes_locked_by(locked_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
