<?php
declare(strict_types=1);

return static function(PDO $pdo):void{
    foreach([
        "ALTER TABLE bdc_wdc_identities ADD COLUMN country VARCHAR(100) NULL AFTER display_name",
        "ALTER TABLE bdc_wdc_identities ADD COLUMN photo_url VARCHAR(1000) NULL AFTER country"
    ] as $sql){try{$pdo->exec($sql);}catch(Throwable){}}
    $pdo->exec("UPDATE bdc_wdc_identities w JOIN bdc_competitors c ON c.id=w.solo_competitor_id SET w.country=COALESCE(w.country,c.country),w.photo_url=COALESCE(w.photo_url,c.photo_url) WHERE w.entry_type='solo'");
};
