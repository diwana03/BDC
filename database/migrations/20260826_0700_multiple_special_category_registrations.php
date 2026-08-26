<?php
declare(strict_types=1);

use App\Services\SpecialCategoryRecoveryService;

return [
    'dependencies'=>[
        dirname(__DIR__,2).'/app/Services/SpecialCategoryRecoveryService.php',
    ],
    'up'=>static function(PDO $pdo):void{
        SpecialCategoryRecoveryService::ensureSchema($pdo);
        $insert=$pdo->prepare("INSERT IGNORE INTO bdc_competitor_special_categories(competitor_id,dance_style,category,source_kind,source_name) VALUES(:competitor,:dance,:category,:kind,:source)");

        $legacy=$pdo->query("SELECT competitor_id,dance_style,special_category FROM bdc_competitor_discipline_profiles WHERE special_category IN ('bachata_rising','bachata_open','bachata_invitational','salsa_rising','salsa_open')")->fetchAll(PDO::FETCH_ASSOC);
        foreach($legacy as $row)$insert->execute(['competitor'=>$row['competitor_id'],'dance'=>$row['dance_style'],'category'=>$row['special_category'],'kind'=>'legacy_profile','source'=>'Separated profile value']);

        $evidence=$pdo->query("SELECT competitor_id,form_kind,payload_json,source_system,source_key FROM bdc_form_sync_submissions WHERE competitor_id IS NOT NULL AND status='completed' AND form_kind IN ('open','amateur')")->fetchAll(PDO::FETCH_ASSOC);
        foreach($evidence as $row){
            $payload=json_decode((string)$row['payload_json'],true);if(!is_array($payload))continue;
            $styles=(array)($payload['styles']??[]);$kind=(string)$row['form_kind'];
            foreach($styles as $style){$dance=strtolower(trim((string)$style));if(!in_array($dance,['bachata','salsa'],true))continue;
                $insert->execute(['competitor'=>$row['competitor_id'],'dance'=>$dance,'category'=>$dance.'_'.($kind==='open'?'open':'rising'),'kind'=>'form_sync','source'=>substr((string)$row['source_system'].' / '.(string)$row['source_key'],0,255)]);
            }
        }

        $recovery=$pdo->query("SELECT competitor_id,dance_style,recovered_category,source_kind,source_name FROM bdc_special_category_recovery WHERE applied_at IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
        foreach($recovery as $row)$insert->execute(['competitor'=>$row['competitor_id'],'dance'=>$row['dance_style'],'category'=>$row['recovered_category'],'kind'=>(string)($row['source_kind']?:'recovery'),'source'=>(string)($row['source_name']?:'Recovery evidence')]);

        // The canonical source is now the multi-row table. The legacy scalar is
        // cleared so no screen can accidentally treat one category as primary.
        $pdo->exec('UPDATE bdc_competitor_discipline_profiles SET special_category=NULL WHERE special_category IS NOT NULL');
    },
];
