<?php
declare(strict_types=1);

use PDO;

return static function(PDO $pdo):void {
    foreach (['bdc_test_scoring_judge_sessions','bdc_scoring_judge_sessions'] as $table) {
        $pdo->exec("DELETE older FROM {$table} older JOIN {$table} newer ON newer.judge_id=older.judge_id AND newer.id>older.id");
        try {
            $pdo->exec("ALTER TABLE {$table} ADD UNIQUE INDEX uq_canonical_judge_session(judge_id)");
        } catch (Throwable) {
            // The Test table and newer Live installations already have an equivalent unique index.
        }
    }
};
