<?php
declare(strict_types=1);
return static function(PDO $pdo):void{
    $profile="'novice','intermediate','advanced','bachata_rising','bachata_open','bachata_invitational','salsa_rising','salsa_open','semi_pro','pro','professional','all_star','unknown'";
    $tier="'novice','intermediate','advanced','all_star','bachata_rising','bachata_open','bachata_invitational','salsa_rising','salsa_open','unknown'";
    $pdo->exec("ALTER TABLE bdc_competitor_discipline_profiles MODIFY current_division ENUM($profile) NOT NULL DEFAULT 'unknown'");
    $pdo->exec("ALTER TABLE bdc_competitors MODIFY current_division ENUM($profile) NOT NULL DEFAULT 'unknown'");
    $pdo->exec("ALTER TABLE bdc_event_points_tiers MODIFY division ENUM($tier) NOT NULL");
};