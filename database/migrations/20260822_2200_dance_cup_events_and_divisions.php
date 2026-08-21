<?php
declare(strict_types=1);

return static function (PDO $pdo): void {
    foreach ([['bdc_dance_cup_events','bdc_dance_cup_competitions'],['bdc_test_dance_cup_events','bdc_test_dance_cup_competitions']] as [$events,$competitions]) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$events}(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(190) NOT NULL,event_date DATE NULL,end_date DATE NULL,venue VARCHAR(190) NULL,country VARCHAR(100) NULL,status VARCHAR(30) NOT NULL DEFAULT 'draft',created_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_dc_event_status(status,event_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1000000");
        foreach (["ALTER TABLE {$competitions} DROP FOREIGN KEY ".($competitions==='bdc_dance_cup_competitions'?'fk_dance_cup_comp_event_live':'fk_dance_cup_comp_event_test'),"ALTER TABLE {$competitions} ADD COLUMN competition_level VARCHAR(30) NOT NULL DEFAULT 'open' AFTER dance_style","ALTER TABLE {$competitions} ADD COLUMN performance_type VARCHAR(30) NOT NULL DEFAULT 'showcase' AFTER competition_level"] as $sql) { try {$pdo->exec($sql);} catch (Throwable) {} }
    }
};
