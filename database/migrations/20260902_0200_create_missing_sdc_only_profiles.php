<?php
declare(strict_types=1);

use App\Services\CompetitorIdentityService;
use App\Services\CouncilResultIdentityService;

return static function(PDO $pdo):void{
    $profiles=[
        ['Sasa','Singapore','follower',['salsa_rising']],
        ['SO YOUNG SHIN (Linda)','South Korea','follower',['salsa_open']],
        ['MITSUHIRO NAKAKOJI','Japan','leader',['salsa_open']],
        ['Mika','Taiwan','unknown',['salsa_rising','salsa_open']],
        ['Carlito','China','unknown',['salsa_open']],
        ['Lanye','China','unknown',['salsa_open']],
        ['Sharleen','China','unknown',['salsa_open']],
        ['Cookie','China','unknown',['salsa_open']],
    ];
    $find=$pdo->prepare("SELECT c.id FROM bdc_competitors c JOIN bdc_result_identities ri ON ri.competitor_id=c.id AND ri.council='sdc' WHERE c.normalised_name=:name LIMIT 1");
    $insert=$pdo->prepare("INSERT INTO bdc_competitors(bdc_id,exact_name,normalised_name,country,dance_role,current_division,status,is_historical) VALUES(NULL,:exact,:normalised,:country,'unknown','unknown','active',0)");
    $profile=$pdo->prepare("INSERT INTO bdc_competitor_discipline_profiles(competitor_id,dance_style,dance_role,current_division) VALUES(:id,'salsa',:role,'unknown') ON DUPLICATE KEY UPDATE dance_role=VALUES(dance_role),current_division='unknown'");
    $clear=$pdo->prepare("DELETE FROM bdc_competitor_special_categories WHERE competitor_id=:id AND dance_style='salsa'");
    $category=$pdo->prepare("INSERT INTO bdc_competitor_special_categories(competitor_id,dance_style,category,source_kind,source_name) VALUES(:id,'salsa',:category,'form_sync','Verified 2026 Salsa registration')");
    $pdo->beginTransaction();
    try{
        foreach($profiles as [$name,$country,$role,$categories]){
            $normalised=CompetitorIdentityService::normaliseCompetitorName($name);$find->execute(['name'=>$normalised]);$id=(int)($find->fetchColumn()?:0);
            if($id<1){$insert->execute(['exact'=>$name,'normalised'=>$normalised,'country'=>$country]);$id=(int)$pdo->lastInsertId();CouncilResultIdentityService::identityForCompetitor($pdo,$id,'salsa');}
            $profile->execute(['id'=>$id,'role'=>$role]);$clear->execute(['id'=>$id]);foreach($categories as $value)$category->execute(['id'=>$id,'category'=>$value]);
        }
        $pdo->commit();
    }catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw $error;}
};
