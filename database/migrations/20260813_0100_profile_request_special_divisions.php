<?php
declare(strict_types=1);

return static function(PDO $pdo):void{
    $profile="'novice','intermediate','advanced','bachata_rising','bachata_open','bachata_invitational','salsa_rising','salsa_open','semi_pro','pro','professional','all_star','unknown'";
    $pdo->exec("ALTER TABLE bdc_profile_requests MODIFY current_division ENUM($profile) NOT NULL DEFAULT 'unknown'");
};
