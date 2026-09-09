<?php
declare(strict_types=1);

return static function(PDO $pdo):void{
    try{
        $pdo->exec('ALTER TABLE bdc_wdc_identities ADD COLUMN original_photo_url VARCHAR(1000) NULL AFTER photo_url');
    }catch(Throwable){}
    $pdo->exec("UPDATE bdc_wdc_identities SET original_photo_url=photo_url WHERE original_photo_url IS NULL AND photo_url IS NOT NULL AND TRIM(photo_url)<>''");
};
