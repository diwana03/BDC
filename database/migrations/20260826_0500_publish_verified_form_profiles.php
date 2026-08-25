<?php
declare(strict_types=1);

use App\Services\BackupService;
use App\Services\CompetitorIdentityService;
use App\Services\SpecialCategoryRecoveryService;

return [
    'dependencies'=>[
        dirname(__DIR__,2).'/app/Services/BackupService.php',
        dirname(__DIR__,2).'/app/Services/CompetitorIdentityService.php',
        dirname(__DIR__,2).'/app/Services/SpecialCategoryRecoveryService.php',
    ],
    'up'=>static function(PDO $pdo):void{
        $profiles=[
            ['bdc_id'=>'BDC-000551','name'=>'JAECHEOL YUN','dance'=>'bachata','role'=>'leader','division'=>'bachata_open'],
            ['bdc_id'=>'BDC-000550','name'=>'LI KWAN ON','dance'=>'bachata','role'=>'leader','division'=>'bachata_rising'],
            ['bdc_id'=>'BDC-000424','name'=>'Jonmichal Bilecki','dance'=>'bachata','role'=>'leader','division'=>'bachata_rising'],
            ['bdc_id'=>'BDC-000548','name'=>'Mathilde Wang','dance'=>'bachata','role'=>'follower','division'=>'bachata_rising'],
            ['bdc_id'=>'BDC-000547','name'=>'Ngamkiat Chainiwattana','dance'=>'bachata','role'=>'follower','division'=>'bachata_rising'],
            ['bdc_id'=>'BDC-000546','name'=>'DO EON KIM','dance'=>'bachata','role'=>'follower','division'=>'bachata_rising'],
            ['bdc_id'=>'BDC-000545','name'=>'Sylvana Lee','dance'=>'salsa','role'=>'follower','division'=>'salsa_rising'],
            ['bdc_id'=>'BDC-000544','name'=>'Olga Demianchick','dance'=>'bachata','role'=>'follower','division'=>'bachata_rising'],
            ['bdc_id'=>'BDC-000523','name'=>'Ángel Jessie','dance'=>'bachata','role'=>'follower','division'=>'bachata_rising'],
            ['bdc_id'=>'BDC-000542','name'=>'Edward Lau','dance'=>'bachata','role'=>'leader','division'=>'bachata_rising'],
            ['bdc_id'=>'BDC-000541','name'=>'MJ','dance'=>'bachata','role'=>'follower','division'=>'bachata_open'],
            ['bdc_id'=>'BDC-000540','name'=>'MAMONG','dance'=>'bachata','role'=>'leader','division'=>'bachata_open'],
            ['bdc_id'=>'BDC-000540','name'=>'MAMONG','dance'=>'salsa','role'=>'leader','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000539','name'=>'Maki Brophy','dance'=>'bachata','role'=>'follower','division'=>'bachata_open'],
            ['bdc_id'=>'BDC-000538','name'=>'Henil Bhanderi','dance'=>'bachata','role'=>'leader','division'=>'bachata_open'],
            ['bdc_id'=>'BDC-000537','name'=>'Gokul Krishnan','dance'=>'salsa','role'=>'leader','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000536','name'=>'Madya','dance'=>'salsa','role'=>'follower','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000535','name'=>'Lauren Kim','dance'=>'bachata','role'=>'follower','division'=>'bachata_open'],
            ['bdc_id'=>'BDC-000534','name'=>'Yeonjeong Lee','dance'=>'bachata','role'=>'follower','division'=>'bachata_open'],
            ['bdc_id'=>'BDC-000533','name'=>'Cindy Yu','dance'=>'salsa','role'=>'follower','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000532','name'=>'JIHYO','dance'=>'salsa','role'=>'follower','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000531','name'=>'TAN LI MIN','dance'=>'salsa','role'=>'follower','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000530','name'=>'Nadiya Yagfarova','dance'=>'salsa','role'=>'follower','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000529','name'=>'Alena Studenok','dance'=>'bachata','role'=>'follower','division'=>'bachata_open'],
            ['bdc_id'=>'BDC-000522','name'=>'Eyin Goh','dance'=>'salsa','role'=>'follower','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000527','name'=>'Ademola Okeowo','dance'=>'salsa','role'=>'leader','division'=>'salsa_open'],
            ['bdc_id'=>'BDC-000526','name'=>'Melanie Held','dance'=>'bachata','role'=>'follower','division'=>'bachata_open'],
            ['bdc_id'=>'BDC-000525','name'=>'Anan Anwar','dance'=>'bachata','role'=>'leader','division'=>'bachata_open'],
            ['bdc_id'=>'BDC-000524','name'=>'Olga Sheina','dance'=>'bachata','role'=>'follower','division'=>'bachata_open'],
        ];
        $duplicates=[
            ['keep'=>'BDC-000424','duplicate'=>'BDC-000549','name'=>'Jonmichal Bilecki'],
            ['keep'=>'BDC-000523','duplicate'=>'BDC-000543','name'=>'Ángel Jessie'],
            ['keep'=>'BDC-000522','duplicate'=>'BDC-000528','name'=>'Eyin Goh'],
        ];

        SpecialCategoryRecoveryService::ensureSchema($pdo);
        (new BackupService())->createDatabaseBackup(0);
        $find=$pdo->prepare('SELECT * FROM bdc_competitors WHERE bdc_id=:bdc LIMIT 1');
        $pdo->beginTransaction();
        try{
            foreach($duplicates as $pair){
                $find->execute(['bdc'=>$pair['keep']]);$keep=$find->fetch(PDO::FETCH_ASSOC);
                $find->execute(['bdc'=>$pair['duplicate']]);$duplicate=$find->fetch(PDO::FETCH_ASSOC);
                if(!$keep)throw new RuntimeException('Verified keep identity is missing: '.$pair['keep']);
                if(CompetitorIdentityService::normaliseCompetitorName((string)$keep['exact_name'])!==CompetitorIdentityService::normaliseCompetitorName($pair['name']))throw new RuntimeException('Verified keep identity name mismatch: '.$pair['keep']);
                if(!$duplicate)continue;
                if(CompetitorIdentityService::normaliseCompetitorName((string)$duplicate['exact_name'])!==CompetitorIdentityService::normaliseCompetitorName($pair['name']))throw new RuntimeException('Verified duplicate identity name mismatch: '.$pair['duplicate']);
                $keepId=(int)$keep['id'];$duplicateId=(int)$duplicate['id'];
                foreach(['bdc_claims','bdc_event_registrations','bdc_participant_results','bdc_point_adjustment_requests','bdc_point_transactions','bdc_profile_requests','bdc_form_sync_submissions'] as $table){
                    $pdo->prepare("UPDATE {$table} SET competitor_id=:keep WHERE competitor_id=:duplicate")->execute(['keep'=>$keepId,'duplicate'=>$duplicateId]);
                }
                $pdo->prepare("INSERT INTO bdc_competitor_discipline_profiles(competitor_id,dance_style,dance_role,current_division) SELECT :keep,dance_style,dance_role,current_division FROM bdc_competitor_discipline_profiles WHERE competitor_id=:duplicate ON DUPLICATE KEY UPDATE dance_role=IF(dance_role='unknown',VALUES(dance_role),dance_role),current_division=IF(current_division IN('unknown','novice'),VALUES(current_division),current_division)")->execute(['keep'=>$keepId,'duplicate'=>$duplicateId]);
                $pdo->prepare("UPDATE bdc_competitors k JOIN bdc_competitors d ON d.id=:duplicate SET k.country=COALESCE(NULLIF(TRIM(k.country),''),d.country),k.instagram=COALESCE(NULLIF(TRIM(k.instagram),''),d.instagram),k.email=COALESCE(NULLIF(TRIM(k.email),''),d.email),k.phone=COALESCE(NULLIF(TRIM(k.phone),''),d.phone),k.photo_url=COALESCE(NULLIF(TRIM(k.photo_url),''),d.photo_url),k.career_group_id=COALESCE(k.career_group_id,d.career_group_id),k.admin_notes=TRIM(CONCAT(COALESCE(k.admin_notes,''),'\nVerified form duplicate ',d.bdc_id,' consolidated on ',NOW())) WHERE k.id=:keep")->execute(['keep'=>$keepId,'duplicate'=>$duplicateId]);
                $pdo->prepare('DELETE FROM bdc_competitor_discipline_profiles WHERE competitor_id=:duplicate')->execute(['duplicate'=>$duplicateId]);
                $pdo->prepare('DELETE FROM bdc_competitors WHERE id=:duplicate')->execute(['duplicate'=>$duplicateId]);
            }

            $profile=$pdo->prepare("INSERT INTO bdc_competitor_discipline_profiles(competitor_id,dance_style,dance_role,current_division) VALUES(:competitor,:dance,:role,:division) ON DUPLICATE KEY UPDATE dance_role=VALUES(dance_role),current_division=VALUES(current_division),updated_at=NOW()");
            $legacy=$pdo->prepare('UPDATE bdc_competitors SET dance_role=:role,current_division=:division,status=IF(status=\'archived\',status,\'active\') WHERE id=:competitor');
            $record=$pdo->prepare("INSERT INTO bdc_special_category_recovery(audit_log_id,competitor_id,dance_style,recovered_category,audit_created_at,source_kind,source_name,before_category,applied_at) VALUES(NULL,:competitor,:dance,:division,NULL,'data_entry','2026-08-26 verified 28-profile correction',:before,NOW())");
            $current=$pdo->prepare('SELECT current_division FROM bdc_competitor_discipline_profiles WHERE competitor_id=:competitor AND dance_style=:dance');
            foreach($profiles as $row){
                $find->execute(['bdc'=>$row['bdc_id']]);$competitor=$find->fetch(PDO::FETCH_ASSOC);
                if(!$competitor)throw new RuntimeException('Verified identity is missing: '.$row['bdc_id']);
                if(CompetitorIdentityService::normaliseCompetitorName((string)$competitor['exact_name'])!==CompetitorIdentityService::normaliseCompetitorName($row['name']))throw new RuntimeException('Verified identity name mismatch: '.$row['bdc_id']);
                $id=(int)$competitor['id'];$current->execute(['competitor'=>$id,'dance'=>$row['dance']]);$before=(string)($current->fetchColumn()?:'unknown');
                $profile->execute(['competitor'=>$id,'dance'=>$row['dance'],'role'=>$row['role'],'division'=>$row['division']]);
                if($row['dance']==='bachata')$legacy->execute(['role'=>$row['role'],'division'=>$row['division'],'competitor'=>$id]);
                $record->execute(['competitor'=>$id,'dance'=>$row['dance'],'division'=>$row['division'],'before'=>$before]);
            }
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    },
];
