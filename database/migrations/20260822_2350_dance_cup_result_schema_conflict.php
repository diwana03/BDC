<?php
declare(strict_types=1);

return static function(PDO $pdo):void{
    foreach(['bdc_dance_cup','bdc_test_dance_cup'] as $prefix){
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}_scoring_results(
            competition_id BIGINT UNSIGNED NOT NULL,
            entry_id BIGINT UNSIGNED NOT NULL,
            total_score DECIMAL(12,2) NOT NULL DEFAULT 0,
            placement INT UNSIGNED NOT NULL,
            calculated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(competition_id,entry_id),
            INDEX idx_dc_scoring_result_place(competition_id,placement)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // dev319 Test installations may already contain calculated rows in the
    // short-lived test table. Preserve them when moving to the safe name.
    $column=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bdc_test_dance_cup_results' AND COLUMN_NAME='competition_id'");
    $column->execute();
    if((int)$column->fetchColumn()>0){
        $pdo->exec("INSERT IGNORE INTO bdc_test_dance_cup_scoring_results(competition_id,entry_id,total_score,placement,calculated_at) SELECT competition_id,entry_id,total_score,placement,calculated_at FROM bdc_test_dance_cup_results");
    }
};
