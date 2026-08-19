<?php
declare(strict_types=1);
use PDO;
return static function(PDO $pdo):void {
    foreach(["ALTER TABLE bdc_live_display_sessions ADD COLUMN holding_background_url VARCHAR(500) NULL AFTER group_name","ALTER TABLE bdc_live_display_sessions ADD COLUMN playlist_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER loop_delay_seconds","ALTER TABLE bdc_live_display_sessions ADD COLUMN playlist_position INT UNSIGNED NOT NULL DEFAULT 0 AFTER playlist_enabled"] as $sql){try{$pdo->exec($sql);}catch(Throwable){}}
    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_live_display_playlist_items(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,session_id BIGINT UNSIGNED NOT NULL,event_id BIGINT UNSIGNED NOT NULL,round_id BIGINT UNSIGNED NOT NULL,screen_type ENUM('winners','final_results') NOT NULL,sort_order INT UNSIGNED NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE INDEX uq_live_playlist_order(session_id,sort_order),INDEX idx_live_playlist_session(session_id),INDEX idx_live_playlist_round(round_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
