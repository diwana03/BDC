<?php
declare(strict_types=1);

return static function(PDO $pdo):void{
    try{
        $pdo->exec("ALTER TABLE bdc_judges ADD COLUMN country_code CHAR(2) NULL AFTER country");
    }catch(Throwable){}
};
