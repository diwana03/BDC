<?php
declare(strict_types=1);

return static function(PDO $pdo):void{
    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_scoring_archived_events(
        event_id BIGINT UNSIGNED PRIMARY KEY,
        archived_by BIGINT UNSIGNED NULL,
        archived_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_scoring_archive_event FOREIGN KEY(event_id) REFERENCES bdc_events(id) ON DELETE CASCADE,
        CONSTRAINT fk_scoring_archive_user FOREIGN KEY(archived_by) REFERENCES bdc_users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
