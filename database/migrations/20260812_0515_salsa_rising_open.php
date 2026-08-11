<?php
declare(strict_types=1);
return static function(PDO $pdo):void{
 $special="'novice','intermediate','advanced','all_star','bachata_rising','bachata_open','bachata_invitational','salsa_rising','salsa_open'";
 $specialUnknown="'novice','intermediate','advanced','all_star','bachata_rising','bachata_open','bachata_invitational','salsa_rising','salsa_open','unknown'";
 foreach([
  "ALTER TABLE bdc_scoring_rounds MODIFY division ENUM($special) NOT NULL",
  "ALTER TABLE bdc_registration_desk_links MODIFY division ENUM($special) NOT NULL",
  "ALTER TABLE bdc_registration_desk_activity MODIFY division ENUM($special) NOT NULL",
  "ALTER TABLE bdc_scoring_publications MODIFY division ENUM($specialUnknown) NOT NULL DEFAULT 'unknown'",
  "ALTER TABLE bdc_participant_results MODIFY division ENUM($specialUnknown) NOT NULL DEFAULT 'unknown'",
  "ALTER TABLE bdc_test_scoring_rounds MODIFY division ENUM($special) NOT NULL",
  "ALTER TABLE bdc_test_scoring_publications MODIFY division ENUM($specialUnknown) NOT NULL DEFAULT 'unknown'"
 ] as $sql){$pdo->exec($sql);}
};