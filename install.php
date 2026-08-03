<?php
declare(strict_types=1);

$configPath = __DIR__ . '/config/config.php';
if (!is_file($configPath)) {
    http_response_code(503);
    exit('Configuration is missing. Open <a href="setup.php">setup.php</a> first.');
}

require __DIR__ . '/bootstrap.php';

use App\Core\Csrf;
use App\Core\Database;

$error = '';
$success = '';
$schemaReady = false;
$lockPath = __DIR__ . '/storage/installed.lock';

try {
    $pdo = Database::connection();
    $pdo->query('SELECT 1');
} catch (Throwable $e) {
    $error = 'Database connection failed: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    if (!Csrf::verify($_POST['_csrf'] ?? null)) {
        $error = 'Invalid security token. Refresh the page and try again.';
    } else {
        $name = trim((string) ($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['password_confirmation'] ?? '');

        if ($name === '') {
            $error = 'Enter the administrator name.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid administrator email address.';
        } elseif (strlen($password) < 10) {
            $error = 'The administrator password must contain at least 10 characters.';
        } elseif ($password !== $confirmPassword) {
            $error = 'The passwords do not match.';
        } else {
            try {
                $schema = file_get_contents(__DIR__ . '/database/schema.sql');
                if ($schema === false || trim($schema) === '') {
                    throw new RuntimeException('Cannot read database/schema.sql.');
                }

                $pdo->exec($schema);
                $schemaReady = true;

                $stmt = $pdo->prepare(
                    'INSERT INTO bdc_users (email, password_hash, full_name, role, status)
                     VALUES (:email, :password_hash, :full_name, :role, :status)
                     ON DUPLICATE KEY UPDATE
                        password_hash = VALUES(password_hash),
                        full_name = VALUES(full_name),
                        role = VALUES(role),
                        status = VALUES(status)'
                );
                $stmt->execute([
                    'email' => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'full_name' => $name,
                    'role' => 'super_admin',
                    'status' => 'active',
                ]);

                $settings = $pdo->prepare(
                    'INSERT INTO bdc_settings (setting_key, setting_value)
                     VALUES ("installed_at", :installed_at), ("app_version", "0.3.1")
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
                );
                $settings->execute(['installed_at' => date('Y-m-d H:i:s')]);

                @file_put_contents($lockPath, "BDC Portal 0.3.1 installed at " . date(DATE_ATOM) . PHP_EOL, LOCK_EX);
                $success = 'Installation complete. Your database tables and administrator account are ready.';
            } catch (Throwable $e) {
                $error = 'Installation failed: ' . $e->getMessage();
            }
        }
    }
}

if ($error === '' && !$schemaReady) {
    try {
        $schemaReady = (bool) Database::connection()->query("SHOW TABLES LIKE 'bdc_users'")->fetchColumn();
    } catch (Throwable) {
        $schemaReady = false;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install BDC Portal</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<a href="https://bachatadancecouncil.com/" class="bdc-global-home-link" style="position:fixed;top:12px;right:12px;z-index:1090;display:inline-flex;align-items:center;gap:.35rem;padding:.4rem .7rem;border:1px solid #d7a51f;border-radius:.4rem;background:#d9a928;color:#172033;font:600 14px/1.2 Arial,sans-serif;text-decoration:none;box-shadow:0 2px 8px rgba(0,0,0,.18)">🏠 BDC Home</a>

<div class="container py-5" style="max-width:760px">
<div class="card border-0 shadow-sm"><div class="card-body p-4 p-md-5">
<div class="d-flex justify-content-between align-items-start gap-3 mb-4">
<div><h1 class="h3 mb-1">Install BDC Portal</h1><div class="text-muted">Version 0.3.1</div></div>
<span class="badge text-bg-success">Database connected</span>
</div>

<?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if ($success !== ''): ?>
<div class="alert alert-success"><strong><?= e($success) ?></strong></div>
<div class="d-grid gap-2 d-sm-flex">
<a class="btn btn-dark" href="<?= e(url('/login')) ?>">Open admin login</a>
<a class="btn btn-outline-secondary" href="health.php">Check system health</a>
</div>
<div class="alert alert-warning mt-4 mb-0">For security, delete <code>install.php</code> and <code>setup.php</code> after confirming that login works. Keep <code>config/config.php</code>.</div>
<?php else: ?>
<?php if ($schemaReady): ?><div class="alert alert-info">BDC tables already exist. Submitting this form will create or reset the specified super administrator account.</div><?php endif; ?>
<form method="post" autocomplete="off">
<?= Csrf::field() ?>
<div class="mb-3"><label class="form-label" for="full_name">Administrator name</label><input class="form-control" id="full_name" name="full_name" value="<?= e($_POST['full_name'] ?? '') ?>" required></div>
<div class="mb-3"><label class="form-label" for="email">Administrator email</label><input class="form-control" id="email" type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required></div>
<div class="row g-3">
<div class="col-md-6"><label class="form-label" for="password">Password</label><input class="form-control" id="password" type="password" name="password" minlength="10" required><div class="form-text">Minimum 10 characters.</div></div>
<div class="col-md-6"><label class="form-label" for="password_confirmation">Confirm password</label><input class="form-control" id="password_confirmation" type="password" name="password_confirmation" minlength="10" required></div>
</div>
<button class="btn btn-dark mt-4">Create tables and administrator</button>
</form>
<?php endif; ?>
</div></div>
</div>
</body>
</html>
