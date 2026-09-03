<?php
declare(strict_types=1);

return static function(PDO $pdo):void{
    foreach(['bdc_scoring_rounds','bdc_test_scoring_rounds'] as $table){
        foreach([
            "ALTER TABLE {$table} ADD COLUMN archived_from_status VARCHAR(40) NULL AFTER status",
            "ALTER TABLE {$table} ADD COLUMN archived_at DATETIME NULL AFTER archived_from_status",
            "ALTER TABLE {$table} ADD COLUMN archived_by BIGINT UNSIGNED NULL AFTER archived_at"
        ] as $sql){try{$pdo->exec($sql);}catch(Throwable){}}
    }
};
