<?php
declare(strict_types=1);

use PDO;

return static function(PDO $pdo):void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_country_normalization_archive(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        competitor_id BIGINT UNSIGNED NOT NULL,
        old_country VARCHAR(100) NOT NULL,
        new_country VARCHAR(100) NOT NULL,
        release_key VARCHAR(40) NOT NULL,
        normalized_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_country_normalization_release(competitor_id,release_key),
        KEY idx_country_normalization_competitor(competitor_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $map=[
        'Jakarta, Indonesia'=>'Indonesia',
        'South Korea / Seoul'=>'South Korea',
        'Bangkok'=>'Thailand',
        'Korea / Seoul'=>'South Korea',
        'Korea/Seoul'=>'South Korea',
        'Japan, Tokyo'=>'Japan',
        'Thailand / Bangkok'=>'Thailand',
        'Melbourne Australia'=>'Australia',
        'FRANCE'=>'France',
        'Manila, Philippines'=>'Philippines',
        'JAPAN'=>'Japan',
        'Russia / Ekaterinburg'=>'Russia',
        'Australia / Melbourne'=>'Australia',
        'New Zealand/ Auckland'=>'New Zealand',
        'Japan /Kyoto'=>'Japan',
        'Japan / Tokyo'=>'Japan',
        'Australia/Melbourne'=>'Australia',
        'TAIWAN'=>'Taiwan',
        'ITALY'=>'Italy',
        'Melbourne, Australia'=>'Australia',
        'Tokyo Japan'=>'Japan',
        'Vietnam/ Ho Chi Minh'=>'Vietnam',
        'Australia / Sydney'=>'Australia',
        'Seoul / Korea'=>'South Korea',
        'USA'=>'United States of America',
    ];
    $find=$pdo->prepare('SELECT id,country FROM bdc_competitors WHERE BINARY country=BINARY :country');
    $archive=$pdo->prepare("INSERT IGNORE INTO bdc_country_normalization_archive(competitor_id,old_country,new_country,release_key) VALUES(:id,:old,:new,'dev625')");
    $update=$pdo->prepare('UPDATE bdc_competitors SET country=:new WHERE id=:id AND BINARY country=BINARY :old');
    foreach($map as $old=>$new){
        $find->execute(['country'=>$old]);
        foreach($find->fetchAll() as $row){
            if((string)$row['country']===$new)continue;
            $archive->execute(['id'=>(int)$row['id'],'old'=>(string)$row['country'],'new'=>$new]);
            $update->execute(['new'=>$new,'id'=>(int)$row['id'],'old'=>(string)$row['country']]);
        }
    }
};
