<?php
declare(strict_types=1);
use PDO;
return static function(PDO $pdo):void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_judge_link_deliveries(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,data_mode ENUM('real','test') NOT NULL,round_id BIGINT UNSIGNED NOT NULL,assignment_id BIGINT UNSIGNED NOT NULL,judge_directory_id BIGINT UNSIGNED NULL,channel ENUM('email','whatsapp') NOT NULL,recipient VARCHAR(190) NOT NULL,status VARCHAR(24) NOT NULL,details VARCHAR(500) NULL,sent_by BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_judge_delivery_round(data_mode,round_id),INDEX idx_judge_delivery_assignment(assignment_id),INDEX idx_judge_delivery_created(created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
