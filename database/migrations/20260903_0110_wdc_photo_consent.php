<?php
declare(strict_types=1);

return static function(PDO $pdo):void{
    try{
        $pdo->exec("ALTER TABLE bdc_wdc_identities ADD COLUMN photo_consent TINYINT(1) NULL AFTER biography");
    }catch(Throwable){}
};
