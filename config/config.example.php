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
        // Password is never stored in PHP. Set BDC_DB_PASSWORD in the server
        // environment, or point to a protected file outside public_html.
        'password_file' => '/home/account/.bdc-secrets/database-password',
        'charset' => 'utf8mb4',
    ],
    'security' => [
        'session_timeout_minutes' => 120,
        'password_min_length' => 10,
        'secure_cookies' => true,
    ],
    'results' => [
        // Must be outside both /portal and /BDC_STAGING. Use a different
        // directory per environment so Staging can never overwrite Production files.
        'storage_path' => '/home/account/.bdc-results/production',
    ],
    'backup' => [
        'cron_token' => 'CHANGE_TO_A_LONG_RANDOM_SECRET',
        'scheduled_type' => 'full',
        'keep_per_type' => 10,
    ],
    // Google Form sync uses the BDC_GOOGLE_FORM_SYNC_SECRET environment
    // variable. Keep the secret outside public_html and use the same value in
    // the response spreadsheet's Apps Script Properties.
    // The v1 profile integration uses BDC_PROFILE_INTEGRATION_SECRET. Its
    // long-lived secret signs timestamped requests that expire after 5 minutes.
    'integration' => [
        'profile_api_scopes' => 'competitors:submit,judges:submit',
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
    // Staging only. Provision this Production user with SELECT/SHOW VIEW
    // permissions only. The application contains no Staging-to-Production path.
    'staging_database_sync' => [
        'enabled' => false,
        'schedule' => 'off', // off, daily, weekly
        'quiet_hour' => 3,
        'production_readonly_database' => [
            'host' => 'localhost',
            'port' => 3306,
            'name' => 'production_database',
            'user' => 'production_readonly_user',
            // Set BDC_PRODUCTION_READONLY_DB_PASSWORD, or use a protected file.
            'password_file' => '/home/account/.bdc-secrets/production-readonly-password',
            'charset' => 'utf8mb4',
        ],
    ],
];
