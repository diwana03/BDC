<?php
declare(strict_types=1);

return static function(PDO $pdo):void{
    foreach(['bdc_scoring_judges','bdc_test_scoring_judges'] as $table){
        $tableCheck=$pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table'
        );
        $tableCheck->execute(['table'=>$table]);
        if((int)$tableCheck->fetchColumn()!==1)continue;

        $columnCheck=$pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table AND column_name=\'judge_id\''
        );
        $columnCheck->execute(['table'=>$table]);
        if((int)$columnCheck->fetchColumn()===0){
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN judge_id BIGINT UNSIGNED NULL AFTER id");
        }

        $indexCheck=$pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=:table AND index_name=\'idx_judge_directory\''
        );
        $indexCheck->execute(['table'=>$table]);
        if((int)$indexCheck->fetchColumn()===0){
            $pdo->exec("ALTER TABLE `{$table}` ADD INDEX idx_judge_directory(judge_id)");
        }
    }
};
