<?php
declare(strict_types=1);

return static function (PDO $pdo): void {
    foreach ([
        ['bdc_dance_cup_competitions', 'bdc_dance_cup_criteria', 'bdc_events', 'live'],
        ['bdc_test_dance_cup_competitions', 'bdc_test_dance_cup_criteria', 'bdc_test_events', 'test'],
    ] as [$competitions, $criteria, $events, $suffix]) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$competitions}(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id BIGINT UNSIGNED NOT NULL,
            category_name VARCHAR(190) NOT NULL,
            entry_type VARCHAR(20) NOT NULL DEFAULT 'solo',
            dance_style VARCHAR(80) NULL,
            round_name VARCHAR(40) NOT NULL DEFAULT 'final',
            maximum_score DECIMAL(8,2) NOT NULL DEFAULT 100.00,
            status VARCHAR(30) NOT NULL DEFAULT 'draft',
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_dance_cup_comp_event_{$suffix} FOREIGN KEY(event_id) REFERENCES {$events}(id) ON DELETE CASCADE,
            UNIQUE KEY uq_dance_cup_category(event_id,category_name,round_name),
            INDEX idx_dance_cup_comp_status(status,event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$criteria}(
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            competition_id BIGINT UNSIGNED NOT NULL,
            criterion_name VARCHAR(120) NOT NULL,
            maximum_points DECIMAL(8,2) NOT NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_dance_cup_criterion_comp_{$suffix} FOREIGN KEY(competition_id) REFERENCES {$competitions}(id) ON DELETE CASCADE,
            UNIQUE KEY uq_dance_cup_criterion(competition_id,criterion_name),
            INDEX idx_dance_cup_criterion_order(competition_id,sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
};
