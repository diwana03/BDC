<?php
declare(strict_types=1);

return static function(PDO $pdo):void{
    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_profile_request_dance_cup_categories(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        request_id BIGINT UNSIGNED NOT NULL,
        competition_id BIGINT UNSIGNED NULL,
        event_name VARCHAR(190) NOT NULL,
        category_name VARCHAR(190) NOT NULL,
        dance_style VARCHAR(80) NULL,
        entry_type VARCHAR(20) NOT NULL,
        competition_level VARCHAR(30) NOT NULL DEFAULT 'open',
        partner_or_team_name VARCHAR(190) NULL,
        team_size INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_profile_request_dc_category(request_id,competition_id),
        INDEX idx_profile_request_dc_competition(competition_id),
        CONSTRAINT fk_profile_request_dc_request FOREIGN KEY(request_id) REFERENCES bdc_profile_requests(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
