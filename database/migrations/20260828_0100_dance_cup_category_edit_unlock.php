<?php
declare(strict_types=1);

return static function(PDO $pdo):void{
    $has=static function(string $table,string $column)use($pdo):bool{$q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND COLUMN_NAME=:column');$q->execute(['table'=>$table,'column'=>$column]);return (int)$q->fetchColumn()>0;};
    foreach(['bdc_dance_cup_competitions','bdc_test_dance_cup_competitions'] as $table){
        if(!$has($table,'edit_unlocked_at'))$pdo->exec("ALTER TABLE {$table} ADD edit_unlocked_at DATETIME NULL");
        if(!$has($table,'edit_unlocked_by'))$pdo->exec("ALTER TABLE {$table} ADD edit_unlocked_by BIGINT UNSIGNED NULL");
        if(!$has($table,'edit_unlock_reason'))$pdo->exec("ALTER TABLE {$table} ADD edit_unlock_reason VARCHAR(500) NULL");
    }
};
