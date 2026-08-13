<?php
declare(strict_types=1);

return static function(PDO $pdo):void{
    foreach(['bdc_scoring_rounds','bdc_test_scoring_rounds'] as $table){
        $exists=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table');
        $exists->execute(['table'=>$table]);
        if((int)$exists->fetchColumn()===0)continue;
        $column=$pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=:table AND column_name=\'scheduled_at\'');
        $column->execute(['table'=>$table]);
        if((int)$column->fetchColumn()===0)$pdo->exec("ALTER TABLE {$table} ADD COLUMN scheduled_at DATETIME NULL AFTER round_type");
    }
};
