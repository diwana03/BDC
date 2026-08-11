<?php
declare(strict_types=1);
return static function(PDO $pdo):void{
 $pdo->exec("ALTER TABLE bdc_users MODIFY role ENUM('super_admin','admin','master_scorer','scorer','organiser','competitor') NOT NULL DEFAULT 'competitor'");
};
