<?php
declare(strict_types=1);

return static function(PDO $pdo):void{
    foreach([
        "ALTER TABLE bdc_wdc_identities ADD COLUMN city VARCHAR(120) NULL AFTER country",
        "ALTER TABLE bdc_wdc_identities ADD COLUMN contact_name VARCHAR(190) NULL AFTER city",
        "ALTER TABLE bdc_wdc_identities ADD COLUMN email VARCHAR(190) NULL AFTER contact_name",
        "ALTER TABLE bdc_wdc_identities ADD COLUMN phone VARCHAR(80) NULL AFTER email",
        "ALTER TABLE bdc_wdc_identities ADD COLUMN whatsapp VARCHAR(80) NULL AFTER phone",
        "ALTER TABLE bdc_wdc_identities ADD COLUMN instagram VARCHAR(100) NULL AFTER whatsapp",
        "ALTER TABLE bdc_wdc_identities ADD COLUMN studio_name VARCHAR(190) NULL AFTER instagram",
        "ALTER TABLE bdc_wdc_identities ADD COLUMN member_names TEXT NULL AFTER studio_name",
        "ALTER TABLE bdc_wdc_identities ADD COLUMN biography TEXT NULL AFTER member_names",
        "ALTER TABLE bdc_wdc_identities ADD COLUMN admin_notes TEXT NULL AFTER biography"
    ] as $sql){try{$pdo->exec($sql);}catch(Throwable){}}
    $pdo->exec("UPDATE bdc_wdc_identities w JOIN bdc_competitors c ON c.id=w.solo_competitor_id SET w.country=COALESCE(NULLIF(w.country,''),c.country),w.email=COALESCE(NULLIF(w.email,''),c.email),w.phone=COALESCE(NULLIF(w.phone,''),c.phone),w.instagram=COALESCE(NULLIF(w.instagram,''),c.instagram) WHERE w.entry_type='solo'");
};
