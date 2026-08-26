<?php
declare(strict_types=1);

return static function (PDO $pdo): void {
    $hasColumn=static function(PDO $pdo,string $table,string $column):bool{
        $query=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND COLUMN_NAME=:column');
        $query->execute(['table'=>$table,'column'=>$column]);
        return (int)$query->fetchColumn()>0;
    };
    foreach(['bdc_dance_cup_competitions','bdc_test_dance_cup_competitions'] as $table){
        if(!$hasColumn($pdo,$table,'submitted_by'))$pdo->exec("ALTER TABLE {$table} ADD submitted_by BIGINT UNSIGNED NULL AFTER status");
        if(!$hasColumn($pdo,$table,'submitted_at'))$pdo->exec("ALTER TABLE {$table} ADD submitted_at DATETIME NULL AFTER submitted_by");
        if(!$hasColumn($pdo,$table,'approved_by'))$pdo->exec("ALTER TABLE {$table} ADD approved_by BIGINT UNSIGNED NULL AFTER submitted_at");
        if(!$hasColumn($pdo,$table,'approved_at'))$pdo->exec("ALTER TABLE {$table} ADD approved_at DATETIME NULL AFTER approved_by");
        if(!$hasColumn($pdo,$table,'approval_notes'))$pdo->exec("ALTER TABLE {$table} ADD approval_notes TEXT NULL AFTER approved_at");
        $pdo->exec("UPDATE {$table} SET status='pending_approval',submitted_at=COALESCE(submitted_at,updated_at) WHERE status='submitted'");
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_dance_cup_result_history(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        competition_id BIGINT UNSIGNED NOT NULL,
        event_id BIGINT UNSIGNED NOT NULL,
        entry_id BIGINT UNSIGNED NOT NULL,
        competitor_id BIGINT UNSIGNED NULL,
        display_name VARCHAR(190) NOT NULL,
        dance_style VARCHAR(80) NOT NULL,
        entry_type VARCHAR(20) NOT NULL,
        competition_level VARCHAR(30) NOT NULL,
        gender_eligibility VARCHAR(20) NOT NULL DEFAULT 'mixed',
        placement INT UNSIGNED NOT NULL,
        total_score DECIMAL(12,2) NOT NULL DEFAULT 0,
        approved_by BIGINT UNSIGNED NOT NULL,
        approved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_dc_history_entry(competition_id,entry_id),
        INDEX idx_dc_history_competitor(competitor_id,approved_at),
        INDEX idx_dc_history_event(event_id,placement),
        CONSTRAINT fk_dc_history_competitor FOREIGN KEY(competitor_id) REFERENCES bdc_competitors(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
