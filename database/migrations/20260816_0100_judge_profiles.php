<?php
declare(strict_types=1);

return static function(PDO $pdo):void{
    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_judges(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,judge_code VARCHAR(24) NULL,full_name VARCHAR(160) NOT NULL,country VARCHAR(100) NULL,country_code CHAR(2) NULL,photo_url VARCHAR(500) NULL,instagram VARCHAR(160) NULL,email VARCHAR(190) NULL,status ENUM('active','inactive') NOT NULL DEFAULT 'active',notes TEXT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE INDEX uq_judge_code(judge_code),INDEX idx_judge_name(full_name),INDEX idx_judge_status(status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $columns=[
        'display_name'=>"VARCHAR(160) NULL AFTER full_name",
        'city'=>"VARCHAR(120) NULL AFTER country_code",
        'phone'=>"VARCHAR(80) NULL AFTER email",
        'whatsapp'=>"VARCHAR(80) NULL AFTER phone",
        'preferred_contact'=>"ENUM('email','whatsapp','either','none') NOT NULL DEFAULT 'none' AFTER whatsapp",
        'dance_styles'=>"VARCHAR(120) NULL AFTER preferred_contact",
        'judge_role'=>"ENUM('regular','chief','both') NOT NULL DEFAULT 'regular' AFTER dance_styles",
        'qualified_divisions'=>"TEXT NULL AFTER judge_role",
        'qualified_rounds'=>"VARCHAR(160) NULL AFTER qualified_divisions",
        'languages'=>"VARCHAR(500) NULL AFTER qualified_rounds",
        'biography'=>"TEXT NULL AFTER languages",
        'experience'=>"TEXT NULL AFTER biography",
        'certification'=>"TEXT NULL AFTER experience",
    ];
    foreach($columns as $column=>$definition){
        $check=$pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=\'bdc_judges\' AND column_name=:column');
        $check->execute(['column'=>$column]);
        if((int)$check->fetchColumn()===0)$pdo->exec("ALTER TABLE bdc_judges ADD COLUMN {$column} {$definition}");
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS bdc_judge_profile_requests(
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(160) NOT NULL,
        display_name VARCHAR(160) NULL,
        country VARCHAR(100) NULL,
        city VARCHAR(120) NULL,
        photo_url VARCHAR(500) NULL,
        instagram VARCHAR(160) NULL,
        email VARCHAR(190) NULL,
        phone VARCHAR(80) NULL,
        whatsapp VARCHAR(80) NULL,
        preferred_contact ENUM('email','whatsapp','either','none') NOT NULL DEFAULT 'none',
        dance_styles VARCHAR(120) NULL,
        judge_role ENUM('regular','chief','both') NOT NULL DEFAULT 'regular',
        qualified_divisions TEXT NULL,
        qualified_rounds VARCHAR(160) NULL,
        languages VARCHAR(500) NULL,
        biography TEXT NULL,
        experience TEXT NULL,
        certification TEXT NULL,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        admin_notes TEXT NULL,
        reviewed_by BIGINT UNSIGNED NULL,
        reviewed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_judge_profile_request_status(status),
        INDEX idx_judge_profile_request_name(full_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
