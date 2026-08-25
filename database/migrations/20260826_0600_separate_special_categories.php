<?php
declare(strict_types=1);

use App\Services\DivisionProgressionService;
use App\Services\SpecialCategoryRecoveryService;
use App\Services\SpecialCategoryService;

return [
    'dependencies'=>[
        dirname(__DIR__,2).'/app/Services/DivisionProgressionService.php',
        dirname(__DIR__,2).'/app/Services/SpecialCategoryRecoveryService.php',
        dirname(__DIR__,2).'/app/Services/SpecialCategoryService.php',
    ],
    'up'=>static function(PDO $pdo):void{
        SpecialCategoryRecoveryService::ensureSchema($pdo);
        $specials=['bachata_rising','bachata_open','bachata_invitational','salsa_rising','salsa_open'];
        $marks=implode(',',array_fill(0,count($specials),'?'));
        $select=$pdo->prepare("SELECT competitor_id,dance_style,dance_role,current_division FROM bdc_competitor_discipline_profiles WHERE current_division IN ($marks)");
        $select->execute($specials);
        $legacy=$select->fetchAll(PDO::FETCH_ASSOC);
        $store=$pdo->prepare('UPDATE bdc_competitor_discipline_profiles SET special_category=:category WHERE competitor_id=:competitor AND dance_style=:dance');
        foreach($legacy as $row)$store->execute(['category'=>$row['current_division'],'competitor'=>$row['competitor_id'],'dance'=>$row['dance_style']]);
        foreach($legacy as $row){
            $id=(int)$row['competitor_id'];$dance=(string)$row['dance_style'];$role=(string)$row['dance_role'];
            $career=DivisionProgressionService::approvedPermanentDivision($pdo,$id,$role,$dance);
            if(SpecialCategoryService::isSpecial($career)||$career==='unknown')$career='novice';
            $pdo->prepare('UPDATE bdc_competitor_discipline_profiles SET current_division=:career WHERE competitor_id=:competitor AND dance_style=:dance')->execute(['career'=>$career,'competitor'=>$id,'dance'=>$dance]);
            if($dance==='bachata')$pdo->prepare('UPDATE bdc_competitors SET current_division=:career WHERE id=:competitor')->execute(['career'=>$career,'competitor'=>$id]);
        }
    },
];
