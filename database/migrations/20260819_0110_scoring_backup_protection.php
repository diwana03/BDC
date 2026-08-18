<?php
declare(strict_types=1);

use PDO;

return static function(PDO $pdo):void {
    $column=$pdo->query("SHOW COLUMNS FROM bdc_scoring_backups LIKE 'is_protected'")->fetch();
    if(!$column)$pdo->exec("ALTER TABLE bdc_scoring_backups ADD COLUMN is_protected TINYINT(1) NOT NULL DEFAULT 0 AFTER restore_reason");
    $pdo->exec("UPDATE bdc_scoring_backups SET is_protected=1 WHERE backup_type='manual' OR action_name='archive_snapshot'");
};
