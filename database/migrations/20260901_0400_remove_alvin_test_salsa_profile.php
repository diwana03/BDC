<?php
declare(strict_types=1);

return static function(PDO $pdo):void{
    $find=$pdo->prepare("SELECT id FROM bdc_competitors WHERE bdc_id='BDC-000248' AND exact_name='Alvin Foo Dun Zhi' LIMIT 1");
    $find->execute();
    $competitorId=(int)$find->fetchColumn();
    if($competitorId<1)return;

    $officialPoints=$pdo->prepare("SELECT COUNT(*) FROM bdc_point_transactions WHERE competitor_id=:competitor AND dance_style='salsa'");
    $officialPoints->execute(['competitor'=>$competitorId]);
    $officialResults=$pdo->prepare("SELECT COUNT(*) FROM bdc_participant_results WHERE competitor_id=:competitor AND dance_style='salsa'");
    $officialResults->execute(['competitor'=>$competitorId]);
    if((int)$officialPoints->fetchColumn()>0||(int)$officialResults->fetchColumn()>0){
        throw new RuntimeException('Alvin Salsa cleanup stopped because official Salsa history exists. Review the record manually.');
    }

    $pdo->beginTransaction();
    try{
        $deleteCategories=$pdo->prepare("DELETE FROM bdc_competitor_special_categories WHERE competitor_id=:competitor AND dance_style='salsa'");
        $deleteCategories->execute(['competitor'=>$competitorId]);
        $deleteProfile=$pdo->prepare("DELETE FROM bdc_competitor_discipline_profiles WHERE competitor_id=:competitor AND dance_style='salsa'");
        $deleteProfile->execute(['competitor'=>$competitorId]);
        $deleteIdentity=$pdo->prepare("DELETE FROM bdc_result_identities WHERE competitor_id=:competitor AND council='sdc'");
        $deleteIdentity->execute(['competitor'=>$competitorId]);
        $pdo->commit();
    }catch(Throwable $error){
        if($pdo->inTransaction())$pdo->rollBack();
        throw $error;
    }
};
