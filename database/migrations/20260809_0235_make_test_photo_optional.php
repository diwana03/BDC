<?php
declare(strict_types=1);

use PDO;

return static function (PDO $pdo): void {
    $tableExists = $pdo->query("SHOW TABLES LIKE 'bdc_test_competitors'")->fetchColumn();
    if ($tableExists === false) {
        return;
    }

    $column = $pdo->query(
        "SELECT COLUMN_NAME
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE()
           AND TABLE_NAME='bdc_test_competitors'
           AND COLUMN_NAME='original_photo_url'
         LIMIT 1"
    )->fetchColumn();

    if ($column === false) {
        $pdo->exec(
            "ALTER TABLE bdc_test_competitors
             ADD COLUMN original_photo_url TEXT NULL"
        );
        return;
    }

    $pdo->exec(
        "ALTER TABLE bdc_test_competitors
         MODIFY COLUMN original_photo_url TEXT NULL"
    );
};
