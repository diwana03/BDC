<?php
declare(strict_types=1);

return static function(PDO $pdo):void{
    $target=$pdo->prepare("SELECT c.id,c.exact_name,ri.identity_code FROM bdc_competitors c JOIN bdc_result_identities ri ON ri.competitor_id=c.id AND ri.council='sdc' WHERE c.id=613 AND c.bdc_id IS NULL LIMIT 1");
    $target->execute();$competitor=$target->fetch();
    if(!$competitor||!hash_equals('Carlito',(string)$competitor['exact_name'])||!hash_equals('SDC-000096',(string)$competitor['identity_code']))throw new RuntimeException('Carlito SDC-only identity did not match the approved target.');
    $judges=$pdo->prepare("SELECT id,full_name,display_name,photo_url,original_photo_url FROM bdc_judges WHERE status='active' AND (LOWER(TRIM(full_name))='carlito' OR LOWER(TRIM(COALESCE(display_name,'')))='carlito') ORDER BY id");
    $judges->execute();$matches=$judges->fetchAll();
    if(count($matches)!==1)throw new RuntimeException('Carlito judge photo copy requires exactly one active Judge Database match.');
    $photo=trim((string)($matches[0]['photo_url']??''));$original=trim((string)($matches[0]['original_photo_url']??''))?:$photo;
    if($photo==='')throw new RuntimeException('Carlito Judge Database profile has no photo to copy.');
    $pdo->prepare('UPDATE bdc_competitors SET photo_url=:photo,original_photo_url=:original WHERE id=:id AND bdc_id IS NULL')->execute(['photo'=>$photo,'original'=>$original,'id'=>(int)$competitor['id']]);
};
