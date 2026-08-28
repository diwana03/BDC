<?php
declare(strict_types=1);

return static function(PDO $pdo):void{
    $column=$pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bdc_profile_request_dance_cup_categories' AND COLUMN_NAME='registration_status'");
    $column->execute();
    if((int)$column->fetchColumn()===0){
        $pdo->exec("ALTER TABLE bdc_profile_request_dance_cup_categories ADD registration_status ENUM('submitted','under_review','approved','rejected') NOT NULL DEFAULT 'submitted' AFTER competitor_gender, ADD INDEX idx_profile_request_dc_status(registration_status)");
    }
    $pdo->exec("UPDATE bdc_profile_request_dance_cup_categories d JOIN bdc_profile_requests r ON r.id=d.request_id SET d.registration_status='approved' WHERE r.status='approved' AND d.registration_status='submitted'");
};
