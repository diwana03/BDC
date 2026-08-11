<?php
declare(strict_types=1);

use App\Core\Database;

return static function():void{
    $pdo=Database::connection();
    $typeStmt=$pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bdc_scoring_rounds' AND COLUMN_NAME='id' LIMIT 1");
    $roundIdType=strtolower(trim((string)$typeStmt->fetchColumn()));
    if(!preg_match('/^(?:tinyint|smallint|mediumint|int|bigint)(?:\(\d+\))?(?: unsigned)?$/',$roundIdType)){
        throw new RuntimeException('Cannot determine the scoring round ID type for automatic setup.');
    }
    $base="CREATE TABLE IF NOT EXISTS bdc_scoring_round_setup (
        round_id {$roundIdType} NOT NULL PRIMARY KEY,
        confirmed_at DATETIME NULL,
        confirmed_by BIGINT UNSIGNED NULL,
        confirmed_snapshot_hash CHAR(64) NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
    try{
        $pdo->exec($base.", CONSTRAINT fk_bdc_scoring_round_setup_round FOREIGN KEY (round_id) REFERENCES bdc_scoring_rounds(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }catch(PDOException){
        $pdo->exec($base.") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
};
