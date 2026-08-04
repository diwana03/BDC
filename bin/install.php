<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

$root = dirname(__DIR__);
$lock = $root . '/storage/installed.lock';
if (is_file($lock)) {
    fwrite(STDERR, "Installation is locked.\n");
    exit(1);
}
if (!is_file($root . '/config/config.php')) {
    fwrite(STDERR, "Create config/config.php before installation.\n");
    exit(1);
}

require $root . '/bootstrap.php';

use App\Core\Database;
use App\Services\MigrationRunner;

$options = getopt('', ['name:', 'email:']);
$name = trim((string)($options['name'] ?? ''));
$email = strtolower(trim((string)($options['email'] ?? '')));
$password = (string)(getenv('BDC_INSTALL_ADMIN_PASSWORD') ?: '');
if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
    fwrite(STDERR, "Usage: BDC_INSTALL_ADMIN_PASSWORD='strong password' php bin/install.php --name='Admin Name' --email='admin@example.com'\n");
    exit(1);
}

$pdo = Database::connection();
if ($pdo->query("SHOW TABLES LIKE 'bdc_users'")->fetchColumn()) {
    fwrite(STDERR, "Existing BDC tables detected; refusing to install.\n");
    exit(1);
}

$schema = file_get_contents($root . '/database/schema.sql');
if ($schema === false || trim($schema) === '') throw new RuntimeException('Cannot read database/schema.sql.');
$pdo->exec($schema);
(new MigrationRunner($pdo, $root . '/database/migrations'))->run();
$stmt = $pdo->prepare("INSERT INTO bdc_users(email,password_hash,full_name,role,status) VALUES(:email,:password,:name,'super_admin','active')");
$stmt->execute(['email'=>$email,'password'=>password_hash($password,PASSWORD_DEFAULT),'name'=>$name]);
$pdo->prepare("INSERT INTO bdc_settings(setting_key,setting_value) VALUES('installed_at',NOW()),('app_version','2.2.0') ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute();
if (file_put_contents($lock, 'BDC Portal 2.2.0 installed at ' . date(DATE_ATOM) . PHP_EOL, LOCK_EX) === false) {
    throw new RuntimeException('Could not create installation lock.');
}
echo "BDC Portal 2.2.0 installed.\n";
