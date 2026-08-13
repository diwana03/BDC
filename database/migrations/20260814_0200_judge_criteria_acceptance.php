<?php
declare(strict_types=1);

return static function(PDO $pdo):void{
    foreach(['bdc_scoring_judge_sessions','bdc_test_scoring_judge_sessions'] as $table){
        $exists=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table');
        $exists->execute(['table'=>$table]);
        if((int)$exists->fetchColumn()===0)continue;
        foreach([
            'criteria_version'=>"VARCHAR(32) NULL AFTER submitted_at",
            'criteria_accepted_at'=>"DATETIME NULL AFTER criteria_version",
            'unlocked_at'=>"DATETIME NULL AFTER criteria_accepted_at",
            'unlocked_by'=>"BIGINT UNSIGNED NULL AFTER unlocked_at",
            'unlock_reason'=>"VARCHAR(500) NULL AFTER unlocked_by",
        ] as $column=>$definition){
            $check=$pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table AND column_name=:column');
            $check->execute(['table'=>$table,'column'=>$column]);
            if((int)$check->fetchColumn()===0)$pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        }
    }
};
