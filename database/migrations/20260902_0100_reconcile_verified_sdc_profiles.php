<?php
declare(strict_types=1);

use App\Services\CouncilResultIdentityService;

return static function(PDO $pdo):void{
    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_sdc_duplicate_resolution_archive(
        removed_competitor_id BIGINT UNSIGNED PRIMARY KEY,kept_competitor_id BIGINT UNSIGNED NOT NULL,
        removed_identity_json LONGTEXT NULL,removed_profile_json LONGTEXT NULL,removed_categories_json LONGTEXT NULL,
        resolution_note VARCHAR(255) NOT NULL,resolved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $exact=$pdo->prepare('SELECT bdc_id,exact_name FROM bdc_competitors WHERE id=:id LIMIT 1');
    $history=$pdo->prepare("SELECT (SELECT COUNT(*) FROM bdc_participant_results WHERE competitor_id=:a AND dance_style='salsa')+(SELECT COUNT(*) FROM bdc_point_transactions WHERE competitor_id=:b AND dance_style='salsa')");
    $identity=$pdo->prepare("SELECT * FROM bdc_result_identities WHERE competitor_id=:id AND council='sdc'");
    $profile=$pdo->prepare("SELECT * FROM bdc_competitor_discipline_profiles WHERE competitor_id=:id AND dance_style='salsa'");
    $categories=$pdo->prepare("SELECT * FROM bdc_competitor_special_categories WHERE competitor_id=:id AND dance_style='salsa' ORDER BY id");
    $deleteCategories=$pdo->prepare("DELETE FROM bdc_competitor_special_categories WHERE competitor_id=:id AND dance_style='salsa'");
    $deleteProfile=$pdo->prepare("DELETE FROM bdc_competitor_discipline_profiles WHERE competitor_id=:id AND dance_style='salsa'");
    $deleteIdentity=$pdo->prepare("DELETE FROM bdc_result_identities WHERE competitor_id=:id AND council='sdc'");
    $pdo->beginTransaction();
    try{
        // Finish the confirmed non-registration cleanup. Shared BDC records remain untouched.
        foreach([487=>['BDC-000487','Cameron Taylor'],415=>['BDC-000415','Cyrus Norcross'],443=>['BDC-000443','Kazuhiro Watanabe']] as $id=>[$bdc,$name]){
            $exact->execute(['id'=>$id]);$row=$exact->fetch();if(!$row||!hash_equals($bdc,(string)$row['bdc_id'])||!hash_equals($name,(string)$row['exact_name']))throw new RuntimeException('SDC cleanup identity mismatch for '.$id.'.');
            $history->execute(['a'=>$id,'b'=>$id]);if((int)$history->fetchColumn()>0)throw new RuntimeException('Official Salsa history protects '.$bdc.'.');
            $identity->execute(['id'=>$id]);$i=$identity->fetchAll();$profile->execute(['id'=>$id]);$p=$profile->fetchAll();$categories->execute(['id'=>$id]);$c=$categories->fetchAll();
            $pdo->prepare("INSERT INTO bdc_sdc_association_removal_archive(competitor_id,bdc_id,exact_name,identity_json,profile_json,categories_json,approval_note) VALUES(:id,:bdc,:name,:identity,:profile,:categories,'Super Admin confirmed: absent from verified Salsa sheets') ON DUPLICATE KEY UPDATE identity_json=VALUES(identity_json),profile_json=VALUES(profile_json),categories_json=VALUES(categories_json)")->execute(['id'=>$id,'bdc'=>$bdc,'name'=>$name,'identity'=>json_encode($i,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'profile'=>json_encode($p,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'categories'=>json_encode($c,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
            $deleteCategories->execute(['id'=>$id]);$deleteProfile->execute(['id'=>$id]);$deleteIdentity->execute(['id'=>$id]);
        }
        // Keep the oldest stable SDC identity; combine the verified registrations there.
        foreach([[572,576,['salsa_rising'],'Budveen Hewabaddage'],[537,578,['salsa_rising','salsa_open'],'Gokul Krishnan']] as [$keep,$remove,$verifiedCategories,$name]){
            foreach([$keep,$remove] as $id){$exact->execute(['id'=>$id]);$row=$exact->fetch();if(!$row||!hash_equals($name,(string)$row['exact_name']))throw new RuntimeException('SDC duplicate identity mismatch for '.$id.'.');}
            $history->execute(['a'=>$remove,'b'=>$remove]);if((int)$history->fetchColumn()>0)throw new RuntimeException('Official Salsa history protects duplicate '.$remove.'.');
            $identity->execute(['id'=>$remove]);$i=$identity->fetchAll();$profile->execute(['id'=>$remove]);$p=$profile->fetchAll();$categories->execute(['id'=>$remove]);$c=$categories->fetchAll();
            $pdo->prepare("INSERT INTO bdc_sdc_duplicate_resolution_archive(removed_competitor_id,kept_competitor_id,removed_identity_json,removed_profile_json,removed_categories_json,resolution_note) VALUES(:remove,:keep,:identity,:profile,:categories,'Verified registration categories consolidated on oldest SDC identity')")->execute(['remove'=>$remove,'keep'=>$keep,'identity'=>json_encode($i,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'profile'=>json_encode($p,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),'categories'=>json_encode($c,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
            $deleteCategories->execute(['id'=>$remove]);$deleteProfile->execute(['id'=>$remove]);$deleteIdentity->execute(['id'=>$remove]);
            $pdo->prepare("INSERT INTO bdc_competitor_discipline_profiles(competitor_id,dance_style,dance_role,current_division) VALUES(:id,'salsa','unknown','unknown') ON DUPLICATE KEY UPDATE current_division=IF(current_division='novice','unknown',current_division)")->execute(['id'=>$keep]);
            $deleteCategories->execute(['id'=>$keep]);$add=$pdo->prepare("INSERT INTO bdc_competitor_special_categories(competitor_id,dance_style,category,source_kind,source_name) VALUES(:id,'salsa',:category,'form_sync','Verified 2026 Salsa registration')");foreach($verifiedCategories as $category)$add->execute(['id'=>$keep,'category'=>$category]);
        }
        // Clear the incorrect career Novice tag for every person currently carrying an SDC identity.
        $pdo->exec("UPDATE bdc_competitor_discipline_profiles p JOIN bdc_result_identities ri ON ri.competitor_id=p.competitor_id AND ri.council='sdc' SET p.current_division='unknown' WHERE p.dance_style='salsa' AND p.current_division='novice'");
        // Unambiguous existing profiles with photos: add SDC only, never alter their BDC identity.
        foreach([[523,'Ángel Jessie','follower'],[566,'Carlos Troncoso','unknown'],[567,'Bárbara Nicole  Schumacher Alvear','unknown']] as [$id,$name,$role]){
            $exact->execute(['id'=>$id]);$row=$exact->fetch();if(!$row||!hash_equals($name,(string)$row['exact_name']))throw new RuntimeException('SDC addition identity mismatch for '.$id.'.');
            CouncilResultIdentityService::identityForCompetitor($pdo,$id,'salsa');
            $pdo->prepare("INSERT INTO bdc_competitor_discipline_profiles(competitor_id,dance_style,dance_role,current_division) VALUES(:id,'salsa',:role,'unknown') ON DUPLICATE KEY UPDATE dance_role=VALUES(dance_role),current_division=IF(current_division='novice','unknown',current_division)")->execute(['id'=>$id,'role'=>$role]);
            $deleteCategories->execute(['id'=>$id]);$pdo->prepare("INSERT INTO bdc_competitor_special_categories(competitor_id,dance_style,category,source_kind,source_name) VALUES(:id,'salsa','salsa_open','form_sync','Verified 2026 Salsa Open registration')")->execute(['id'=>$id]);
        }
        $pdo->commit();
    }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
};
