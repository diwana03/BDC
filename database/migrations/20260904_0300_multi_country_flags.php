<?php
declare(strict_types=1);

return static function(PDO $pdo):void{
    foreach(['bdc_competitors','bdc_test_competitors','bdc_judges','bdc_wdc_identities','bdc_judge_profile_requests'] as $table){
        try{$pdo->exec("ALTER TABLE {$table} ADD COLUMN countries_json TEXT NULL AFTER country");}catch(Throwable){}
        try{$pdo->exec("UPDATE {$table} SET countries_json=JSON_ARRAY(country) WHERE country IS NOT NULL AND TRIM(country)<>'' AND (countries_json IS NULL OR TRIM(countries_json)='')");}catch(Throwable){}
    }
};
