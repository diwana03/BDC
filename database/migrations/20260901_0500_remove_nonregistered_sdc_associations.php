<?php
declare(strict_types=1);

return static function(PDO $pdo):void{
    $targets=[
        380=>['BDC-000380','anna Galenda'],
        293=>['BDC-000293','Asela (ELLA) Astrelia'],
        325=>['BDC-000325','Asia G'],
        241=>['BDC-000241','Bryon Harianto'],
        291=>['BDC-000291','Firas Ma'],
        396=>['BDC-000396','KEVIN Christian Quinico'],
        260=>['BDC-000260','Piyush Dane'],
        261=>['BDC-000261','Shauqi Iskandar'],
    ];
    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_sdc_association_removal_archive(
        competitor_id BIGINT UNSIGNED PRIMARY KEY,
        bdc_id VARCHAR(24) NOT NULL,
        exact_name VARCHAR(190) NOT NULL,
        identity_json LONGTEXT NULL,
        profile_json LONGTEXT NULL,
        categories_json LONGTEXT NULL,
        approval_note VARCHAR(255) NOT NULL,
        removed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $competitor=$pdo->prepare('SELECT id,bdc_id,exact_name FROM bdc_competitors WHERE id=:id LIMIT 1');
    $points=$pdo->prepare("SELECT COUNT(*) FROM bdc_point_transactions WHERE competitor_id=:id AND dance_style='salsa'");
    $results=$pdo->prepare("SELECT COUNT(*) FROM bdc_participant_results WHERE competitor_id=:id AND dance_style='salsa'");
    $identity=$pdo->prepare("SELECT * FROM bdc_result_identities WHERE competitor_id=:id AND council='sdc'");
    $profile=$pdo->prepare("SELECT * FROM bdc_competitor_discipline_profiles WHERE competitor_id=:id AND dance_style='salsa'");
    $categories=$pdo->prepare("SELECT * FROM bdc_competitor_special_categories WHERE competitor_id=:id AND dance_style='salsa' ORDER BY id");
    $archive=$pdo->prepare("INSERT INTO bdc_sdc_association_removal_archive(competitor_id,bdc_id,exact_name,identity_json,profile_json,categories_json,approval_note) VALUES(:id,:bdc,:name,:identity,:profile,:categories,'Super Admin confirmed removal: not present in verified Salsa registration sheets') ON DUPLICATE KEY UPDATE bdc_id=VALUES(bdc_id),exact_name=VALUES(exact_name)");
    $deleteCategories=$pdo->prepare("DELETE FROM bdc_competitor_special_categories WHERE competitor_id=:id AND dance_style='salsa'");
    $deleteProfile=$pdo->prepare("DELETE FROM bdc_competitor_discipline_profiles WHERE competitor_id=:id AND dance_style='salsa'");
    $deleteIdentity=$pdo->prepare("DELETE FROM bdc_result_identities WHERE competitor_id=:id AND council='sdc'");
    $pdo->beginTransaction();
    try{
        foreach($targets as $id=>[$bdcId,$exactName]){
            $competitor->execute(['id'=>$id]);$row=$competitor->fetch();
            if(!$row||!hash_equals($bdcId,(string)$row['bdc_id'])||!hash_equals($exactName,(string)$row['exact_name']))throw new RuntimeException('SDC cleanup identity mismatch for competitor '.$id.'.');
            $points->execute(['id'=>$id]);$results->execute(['id'=>$id]);
            if((int)$points->fetchColumn()>0||(int)$results->fetchColumn()>0)throw new RuntimeException('SDC cleanup stopped because official Salsa history exists for '.$bdcId.'.');
            $identity->execute(['id'=>$id]);$identityRows=$identity->fetchAll();$profile->execute(['id'=>$id]);$profileRows=$profile->fetchAll();$categories->execute(['id'=>$id]);$categoryRows=$categories->fetchAll();
            $archive->execute(['id'=>$id,'bdc'=>$bdcId,'name'=>$exactName,'identity'=>json_encode($identityRows,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'profile'=>json_encode($profileRows,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'categories'=>json_encode($categoryRows,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
            $deleteCategories->execute(['id'=>$id]);$deleteProfile->execute(['id'=>$id]);$deleteIdentity->execute(['id'=>$id]);
        }
        $pdo->commit();
    }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
};
