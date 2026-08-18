<?php
declare(strict_types=1);
use PDO;
return static function(PDO $pdo):void {
    foreach([
        "ALTER TABLE bdc_live_display_sessions ADD COLUMN active_event_id BIGINT UNSIGNED NULL AFTER event_id",
        "ALTER TABLE bdc_live_display_sessions ADD COLUMN group_name VARCHAR(190) NULL AFTER data_mode",
    ] as $sql){try{$pdo->exec($sql);}catch(Throwable){}}
    $pdo->exec("UPDATE bdc_live_display_sessions SET active_event_id=event_id WHERE active_event_id IS NULL");
    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_live_display_session_events(session_id BIGINT UNSIGNED NOT NULL,event_id BIGINT UNSIGNED NOT NULL,sort_order INT UNSIGNED NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(session_id,event_id),INDEX idx_live_display_member_event(event_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("INSERT IGNORE INTO bdc_live_display_session_events(session_id,event_id,sort_order) SELECT id,event_id,1 FROM bdc_live_display_sessions");
};
