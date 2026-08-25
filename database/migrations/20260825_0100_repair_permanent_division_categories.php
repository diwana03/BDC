<?php
declare(strict_types=1);

use App\Services\DivisionProgressionService;

return [
    'dependencies'=>[
        dirname(__DIR__,2).'/app/Services/DivisionProgressionService.php',
        dirname(__DIR__,2).'/app/Services/SpecialCategoryService.php',
    ],
    'up'=>static function(PDO $pdo):void{
        DivisionProgressionService::repairLegacySpecialCategoryAssignments($pdo);

        // Event categories remain valid in rounds, results, profile requests
        // and publications. They are forbidden only in permanent identities.
        $career="'novice','intermediate','advanced','semi_pro','pro','professional','all_star','unknown'";
        $tables=['bdc_competitors','bdc_competitor_discipline_profiles','bdc_test_competitors'];
        $exists=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND COLUMN_NAME=\'current_division\'');
        foreach($tables as $table){
            $exists->execute(['table'=>$table]);
            if((int)$exists->fetchColumn()===1)$pdo->exec("ALTER TABLE `{$table}` MODIFY current_division ENUM({$career}) NOT NULL DEFAULT 'unknown'");
        }
    },
];
