<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

$bootstrap = (string)file_get_contents($root . '/bootstrap.php');
$htaccess = (string)file_get_contents($root . '/.htaccess');
$health = (string)file_get_contents($root . '/health.php');
$registration = (string)file_get_contents($root . '/event-registration/index.php');

foreach (['X-Content-Type-Options', 'Referrer-Policy', 'Permissions-Policy'] as $header) {
    if (!str_contains($bootstrap, $header)) $failures[] = "Missing security header: {$header}";
}
if (!str_contains($htaccess, 'install-v.*') || !str_contains($htaccess, 'rollback-v.*')) $failures[] = 'Legacy installer access is not denied.';
if (str_contains($health, 'PHP_VERSION') || str_contains($health, "getMessage()")) $failures[] = 'Health endpoint exposes internal details.';
foreach (['FOR UPDATE', "sold_count=:count", 'registration_opens_at', 'sales_start_at'] as $control) {
    if (!str_contains($registration, $control)) $failures[] = "Registration control missing: {$control}";
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "Security smoke checks passed.\n";
