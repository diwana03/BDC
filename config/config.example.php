<?php
declare(strict_types=1);
return [
    'app' => [
        'name' => 'Bachata Dance Council Portal',
        'url' => 'https://bachatadancecouncil.com/portal',
        'base_path' => '/portal',
        'environment' => 'production',
        'debug' => false,
        'timezone' => 'Asia/Singapore',
        'session_name' => 'bdc_portal_session',
    ],
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'zqculgmy_bdcportal',
        'user' => 'zqculgmy_bdcapp',
        'password' => 'CHANGE_ME',
        'charset' => 'utf8mb4',
    ],
    'security' => [
        'session_timeout_minutes' => 120,
        'password_min_length' => 10,
        'secure_cookies' => true,
    ],
    'backup' => [
        'cron_token' => 'CHANGE_TO_A_LONG_RANDOM_SECRET',
        'scheduled_type' => 'full',
        'keep_per_type' => 10,
    ],
    'deployment' => [
        'enabled' => false,
        'repository_path' => '/home/account/BDC_DEV',
        'source_branch' => 'develop',
        'staging_path' => '/home/account/public_html/example/BDC_STAGING',
        'production_path' => '/home/account/public_html/example/portal',
        'backup_path' => '/home/account/deployment_backups',
        'staging_health_url' => 'https://example.com/BDC_STAGING/health.php',
        'production_health_url' => 'https://example.com/portal/health.php',
    ],
];
