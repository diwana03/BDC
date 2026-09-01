<?php
declare(strict_types=1);

return static function(PDO $pdo):void{
    $special="'novice','intermediate','advanced','all_star','bachata_rising','bachata_open','bachata_invitational','salsa_rising','salsa_open','salsa_invitational'";
    $specialUnknown=$special.",'unknown'";
    foreach([
        "ALTER TABLE bdc_scoring_rounds MODIFY division ENUM({$special}) NOT NULL",
        "ALTER TABLE bdc_registration_desk_links MODIFY division ENUM({$special}) NOT NULL",
        "ALTER TABLE bdc_registration_desk_activity MODIFY division ENUM({$special}) NOT NULL",
        "ALTER TABLE bdc_scoring_publications MODIFY division ENUM({$specialUnknown}) NOT NULL DEFAULT 'unknown'",
        "ALTER TABLE bdc_participant_results MODIFY division ENUM({$specialUnknown}) NOT NULL DEFAULT 'unknown'",
        "ALTER TABLE bdc_test_scoring_rounds MODIFY division ENUM({$special}) NOT NULL",
        "ALTER TABLE bdc_test_scoring_publications MODIFY division ENUM({$specialUnknown}) NOT NULL DEFAULT 'unknown'"
    ] as $sql)$pdo->exec($sql);

    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_result_identities(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        competitor_id BIGINT UNSIGNED NOT NULL,
        council ENUM('bdc','sdc') NOT NULL,
        identity_code VARCHAR(32) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_result_identity_competitor(competitor_id,council),
        UNIQUE KEY uq_result_identity_code(council,identity_code),
        CONSTRAINT fk_result_identity_competitor FOREIGN KEY(competitor_id) REFERENCES bdc_competitors(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("INSERT IGNORE INTO bdc_result_identities(competitor_id,council,identity_code)
        SELECT id,'bdc',bdc_id FROM bdc_competitors WHERE bdc_id IS NOT NULL AND bdc_id<>''");

    $salsa=$pdo->query("SELECT DISTINCT competitor_id FROM (
        SELECT competitor_id FROM bdc_competitor_discipline_profiles WHERE dance_style='salsa'
        UNION SELECT competitor_id FROM bdc_participant_results WHERE dance_style='salsa'
        UNION SELECT competitor_id FROM bdc_point_transactions WHERE dance_style='salsa'
    ) existing_salsa WHERE competitor_id IS NOT NULL ORDER BY competitor_id")->fetchAll(PDO::FETCH_COLUMN);
    $next=(int)$pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(identity_code,5) AS UNSIGNED)),0)+1 FROM bdc_result_identities WHERE council='sdc' AND identity_code LIKE 'SDC-%'")->fetchColumn();
    $insert=$pdo->prepare("INSERT IGNORE INTO bdc_result_identities(competitor_id,council,identity_code) VALUES(:competitor,'sdc',:code)");
    foreach($salsa as $competitorId)$insert->execute(['competitor'=>(int)$competitorId,'code'=>'SDC-'.str_pad((string)$next++,6,'0',STR_PAD_LEFT)]);

    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_wdc_identities(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        identity_code VARCHAR(32) NOT NULL,
        entry_type ENUM('solo','couple','duo','pro_am','team') NOT NULL,
        display_name VARCHAR(190) NOT NULL,
        normalised_name VARCHAR(190) NOT NULL,
        solo_competitor_id BIGINT UNSIGNED NULL,
        status ENUM('active','archived') NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_wdc_code(identity_code),
        INDEX idx_wdc_named_entry(entry_type,normalised_name),
        UNIQUE KEY uq_wdc_solo(solo_competitor_id,entry_type),
        CONSTRAINT fk_wdc_solo_competitor FOREIGN KEY(solo_competitor_id) REFERENCES bdc_competitors(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    foreach(['bdc_dance_cup_entries','bdc_test_dance_cup_entries'] as $table){
        try{$pdo->exec("ALTER TABLE {$table} ADD COLUMN wdc_identity_id BIGINT UNSIGNED NULL AFTER competitor_id");}catch(Throwable){}
        try{$pdo->exec("ALTER TABLE {$table} ADD INDEX idx_dc_entry_wdc(wdc_identity_id)");}catch(Throwable){}
    }
    try{$pdo->exec("ALTER TABLE bdc_dance_cup_result_history ADD COLUMN wdc_identity_id BIGINT UNSIGNED NULL AFTER competitor_id");}catch(Throwable){}
    try{$pdo->exec("ALTER TABLE bdc_dance_cup_result_history ADD INDEX idx_dc_history_wdc(wdc_identity_id,approved_at)");}catch(Throwable){}

    $history=$pdo->query("SELECT id,entry_type,display_name,competitor_id FROM bdc_dance_cup_result_history WHERE wdc_identity_id IS NULL ORDER BY approved_at,id")->fetchAll();
    $findNamed=$pdo->prepare("SELECT id FROM bdc_wdc_identities WHERE entry_type=:type AND normalised_name=:name LIMIT 1");
    $findSolo=$pdo->prepare("SELECT id FROM bdc_wdc_identities WHERE entry_type='solo' AND solo_competitor_id=:competitor LIMIT 1");
    $createWdc=$pdo->prepare("INSERT INTO bdc_wdc_identities(identity_code,entry_type,display_name,normalised_name,solo_competitor_id) VALUES(:code,:type,:display,:normal,:competitor)");
    $linkHistory=$pdo->prepare("UPDATE bdc_dance_cup_result_history SET wdc_identity_id=:wdc WHERE id=:id");
    $nextWdc=(int)$pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(identity_code,5) AS UNSIGNED)),0)+1 FROM bdc_wdc_identities WHERE identity_code LIKE 'WDC-%'")->fetchColumn();
    foreach($history as $row){
        $type=in_array((string)$row['entry_type'],['solo','couple','duo','pro_am','team'],true)?(string)$row['entry_type']:'solo';
        $display=trim((string)preg_replace('/\s+/u',' ',(string)$row['display_name']));
        $lower=function_exists('mb_strtolower')?mb_strtolower($display,'UTF-8'):strtolower($display);
        $normal=trim((string)preg_replace('/[^\pL\pN]+/u',' ',$lower));
        if($type==='solo'&&!empty($row['competitor_id'])){$findSolo->execute(['competitor'=>(int)$row['competitor_id']]);$wdc=(int)$findSolo->fetchColumn();}
        else{$findNamed->execute(['type'=>$type,'name'=>$normal]);$wdc=(int)$findNamed->fetchColumn();}
        if(!$wdc){$createWdc->execute(['code'=>'WDC-'.str_pad((string)$nextWdc++,6,'0',STR_PAD_LEFT),'type'=>$type,'display'=>$display,'normal'=>$normal,'competitor'=>$type==='solo'&&!empty($row['competitor_id'])?(int)$row['competitor_id']:null]);$wdc=(int)$pdo->lastInsertId();}
        $linkHistory->execute(['wdc'=>$wdc,'id'=>$row['id']]);
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_wdc_championship_points(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        wdc_identity_id BIGINT UNSIGNED NOT NULL,
        competition_id BIGINT UNSIGNED NOT NULL,
        entry_id BIGINT UNSIGNED NOT NULL,
        event_id BIGINT UNSIGNED NOT NULL,
        division VARCHAR(30) NOT NULL,
        placement INT UNSIGNED NOT NULL,
        points DECIMAL(8,2) NOT NULL,
        awarded_by BIGINT UNSIGNED NULL,
        awarded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_wdc_points_entry(competition_id,entry_id),
        INDEX idx_wdc_points_identity(wdc_identity_id,awarded_at),
        CONSTRAINT fk_wdc_points_identity FOREIGN KEY(wdc_identity_id) REFERENCES bdc_wdc_identities(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("INSERT IGNORE INTO bdc_wdc_championship_points(wdc_identity_id,competition_id,entry_id,event_id,division,placement,points,awarded_by,awarded_at)
        SELECT wdc_identity_id,competition_id,entry_id,event_id,'open',placement,
            CASE placement WHEN 1 THEN 10 WHEN 2 THEN 8 WHEN 3 THEN 6 WHEN 4 THEN 4 WHEN 5 THEN 2 ELSE 1 END,
            approved_by,approved_at
        FROM bdc_dance_cup_result_history
        WHERE competition_level='open' AND wdc_identity_id IS NOT NULL AND placement>0");
};
