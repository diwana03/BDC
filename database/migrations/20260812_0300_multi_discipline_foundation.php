<?php
declare(strict_types=1);
return static function(PDO $pdo):void{
    $columns=[
        ['bdc_point_transactions','dance_style',"ENUM('bachata','salsa') NOT NULL DEFAULT 'bachata' AFTER event_id"],
        ['bdc_participant_results','dance_style',"ENUM('bachata','salsa','dance_cup') NOT NULL DEFAULT 'bachata' AFTER event_id"],
        ['bdc_scoring_rounds','dance_style',"ENUM('bachata','salsa') NOT NULL DEFAULT 'bachata' AFTER event_id"],
    ];
    foreach($columns as [$table,$column,$definition]){
        $stmt=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND COLUMN_NAME=:column");
        $stmt->execute(['table'=>$table,'column'=>$column]);
        if((int)$stmt->fetchColumn()===0)$pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    }
    foreach([
        "CREATE INDEX idx_points_dance_division_role ON bdc_point_transactions(dance_style,division,dance_role)",
        "CREATE INDEX idx_rounds_event_dance ON bdc_scoring_rounds(event_id,dance_style,division,round_type)",
        "CREATE INDEX idx_results_event_dance ON bdc_participant_results(event_id,dance_style,division,dance_role)"
    ] as $sql){try{$pdo->exec($sql);}catch(PDOException $e){if(!str_contains(strtolower($e->getMessage()),'duplicate'))throw $e;}}
    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_dance_cup_results(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        event_id BIGINT UNSIGNED NOT NULL,
        competitor_id BIGINT UNSIGNED NULL,
        entrant_name VARCHAR(190) NOT NULL,
        category_name VARCHAR(190) NULL,
        placement INT UNSIGNED NULL,
        notes TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_dance_cup_event FOREIGN KEY(event_id) REFERENCES bdc_events(id) ON DELETE CASCADE,
        CONSTRAINT fk_dance_cup_competitor FOREIGN KEY(competitor_id) REFERENCES bdc_competitors(id) ON DELETE SET NULL,
        INDEX idx_dance_cup_event(event_id),INDEX idx_dance_cup_competitor(competitor_id),INDEX idx_dance_cup_placement(placement)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};