<?php
declare(strict_types=1);
return static function(PDO $pdo):void{
    foreach([
        ['bdc_registration_desk_links','dance_style',"ENUM('bachata','salsa') NOT NULL DEFAULT 'bachata' AFTER event_id"],
        ['bdc_event_points_tiers','dance_style',"ENUM('bachata','salsa') NOT NULL DEFAULT 'bachata' AFTER event_id"],
    ] as [$table,$column,$definition]){
        $exists=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND COLUMN_NAME=:column");
        $exists->execute(['table'=>$table,'column'=>$column]);
        if((int)$exists->fetchColumn()===0)$pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    }
    /* Replace legacy event/division uniqueness where present so Salsa and Bachata can coexist. */
    foreach(['bdc_registration_desk_links','bdc_event_points_tiers'] as $table){
        $idx=$pdo->prepare("SELECT INDEX_NAME,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND NON_UNIQUE=0 GROUP BY INDEX_NAME");
        $idx->execute(['table'=>$table]);
        foreach($idx->fetchAll(PDO::FETCH_ASSOC) as $row){
            if($row['INDEX_NAME']==='PRIMARY')continue;
            if(in_array($row['cols'],['event_id,division','event_id,division,dance_role'],true)){
                $pdo->exec("ALTER TABLE {$table} DROP INDEX `".str_replace('`','',$row['INDEX_NAME'])."`");
            }
        }
    }
    try{$pdo->exec("CREATE UNIQUE INDEX uq_registration_desk_dance ON bdc_registration_desk_links(event_id,dance_style,division)");}catch(PDOException $e){if(!str_contains(strtolower($e->getMessage()),'duplicate'))throw $e;}
    try{$pdo->exec("CREATE UNIQUE INDEX uq_event_points_tier_dance ON bdc_event_points_tiers(event_id,dance_style,division,dance_role)");}catch(PDOException $e){if(!str_contains(strtolower($e->getMessage()),'duplicate'))throw $e;}
};