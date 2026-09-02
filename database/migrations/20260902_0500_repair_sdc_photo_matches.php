<?php
declare(strict_types=1);

return static function(PDO $pdo):void{
    $targets=[
        'sdc-000092-drive-1glRiIBNOVEQkG8SSNzrIyf4hRD6dE289'=>[609,'Sasa','SDC-000092'],
        'sdc-000093-drive-10Fy0M5QyA5iGbwhVjRv2JhJOU0WVvZFz'=>[610,'SO YOUNG SHIN (Linda)','SDC-000093'],
    ];
    $identity=$pdo->prepare("SELECT c.id,c.exact_name FROM bdc_result_identities ri JOIN bdc_competitors c ON c.id=ri.competitor_id WHERE ri.council='sdc' AND ri.identity_code=:sdc AND c.id=:id AND c.status<>'archived'");
    $repair=$pdo->prepare("UPDATE bdc_profile_integration_updates u JOIN bdc_profile_integration_batches b ON b.id=u.batch_id SET u.target_id=:target,u.match_status='matched',u.candidate_ids_json=NULL WHERE b.batch_key='sdc-missing-photos-20260902-01' AND b.source_system='sdc-photo-audit' AND u.entity_type='competitor' AND u.source_key=:source AND u.status='pending' AND u.match_status='new'");
    foreach($targets as $source=>[$id,$name,$sdc]){$identity->execute(['sdc'=>$sdc,'id'=>$id]);$row=$identity->fetch();if(!$row||!hash_equals($name,(string)$row['exact_name']))throw new RuntimeException('SDC photo match repair identity mismatch for '.$sdc.'.');$repair->execute(['target'=>$id,'source'=>$source]);}
};
