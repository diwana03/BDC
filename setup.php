<?php
declare(strict_types=1);

$configFile = __DIR__ . '/config/config.php';
$lockPath = __DIR__ . '/storage/installed.lock';
if (is_file($lockPath) || is_file($configFile)) {
    http_response_code(404);
    exit('Not found.');
}
$message = '';
$error = '';
$tested = false;

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function writeConfig(array $data, string $path): void
{
    $export = var_export($data, true);
    $contents = "<?php\n";
    $contents .= "declare(strict_types=1);\n\n";
    $contents .= "return " . $export . ";\n";

    if (file_put_contents($path, $contents, LOCK_EX) === false) {
        throw new RuntimeException(
            'Could not write config/config.php. Set the config folder permission to 0755, or create config.php manually.'
        );
    }

    @chmod($path, 0640);
}

function writeDatabaseSecret(string $password, string $path, string $applicationRoot): void
{
    $resolvedDirectory = dirname($path);
    if ($path === '' || $path[0] !== '/') {
        throw new RuntimeException('The database password file must use an absolute server path.');
    }
    $normalisedRoot = rtrim(str_replace('\\', '/', $applicationRoot), '/') . '/';
    $normalisedPath = str_replace('\\', '/', $path);
    if (str_starts_with($normalisedPath, $normalisedRoot)) {
        throw new RuntimeException('The password file must be outside the public application directory.');
    }
    if (!is_dir($resolvedDirectory) && !mkdir($resolvedDirectory, 0700, true)) {
        throw new RuntimeException('Could not create the protected secret directory.');
    }
    if (file_put_contents($path, $password . "\n", LOCK_EX) === false) {
        throw new RuntimeException('Could not write the protected database password file.');
    }
    @chmod($path, 0600);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim((string) ($_POST['host'] ?? 'localhost'));
    $port = (int) ($_POST['port'] ?? 3306);
    $name = trim((string) ($_POST['database'] ?? ''));
    $user = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordFile = trim((string) ($_POST['password_file'] ?? dirname(__DIR__, 2) . '/.bdc-secrets/database-password'));
    $basePath = '/' . trim((string) ($_POST['base_path'] ?? 'portal'), '/');

    if ($host === '' || $name === '' || $user === '' || $password === '') {
        $error = 'Complete all database fields.';
    } else {
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
            $pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->query('SELECT 1');
            $tested = true;

            if (isset($_POST['save'])) {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $hostName = $_SERVER['HTTP_HOST'] ?? 'bachatadancecouncil.com';

                writeDatabaseSecret($password, $passwordFile, __DIR__);
                $config = [
                    'app' => [
                        'name' => 'Bachata Dance Council Portal',
                        'url' => $scheme . '://' . $hostName . $basePath,
                        'base_path' => $basePath,
                        'environment' => 'production',
                        'debug' => false,
                        'timezone' => 'Asia/Singapore',
                        'session_name' => 'bdc_portal_session',
                    ],
                    'database' => [
                        'host' => $host,
                        'port' => $port,
                        'name' => $name,
                        'user' => $user,
                        'password_file' => $passwordFile,
                        'charset' => 'utf8mb4',
                    ],
                    'security' => [
                        'session_timeout_minutes' => 120,
                        'password_min_length' => 10,
                    ],
                ];

                writeConfig($config, $configFile);
                header('Location: install.php');
                exit;
            }

            $message = 'Database connection successful. Click “Save and Continue”.';
        } catch (PDOException $e) {
            $code = (string) $e->getCode();
            $raw = $e->getMessage();

            if (str_contains($raw, '1045')) {
                $error = 'MySQL rejected the username or password. Reset the MySQL user password in cPanel, then enter the same new password here.';
            } elseif (str_contains($raw, '1049')) {
                $error = 'The database name was not found. Confirm the full cPanel database name.';
            } elseif (str_contains($raw, '2002')) {
                $error = 'The database host could not be reached. Try localhost first, then confirm the MySQL host with Bluehost.';
            } elseif (str_contains($raw, '1044')) {
                $error = 'The user does not have permission to access this database. Add the user to the database with ALL PRIVILEGES.';
            } else {
                $error = 'Connection failed: ' . $raw;
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$defaults = [
    'host' => $_POST['host'] ?? 'localhost',
    'port' => $_POST['port'] ?? '3306',
    'database' => $_POST['database'] ?? 'zqculgmy_bdcportal',
    'username' => $_POST['username'] ?? 'zqculgmy_bdcapp',
    'password_file' => $_POST['password_file'] ?? dirname(__DIR__, 2) . '/.bdc-secrets/database-password',
    'base_path' => $_POST['base_path'] ?? 'portal',
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>BDC Portal Database Setup</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<a href="https://bachatadancecouncil.com/" class="bdc-global-home-link" style="position:fixed;top:12px;right:12px;z-index:1090;display:inline-flex;align-items:center;gap:.35rem;padding:.4rem .7rem;border:1px solid #d7a51f;border-radius:.4rem;background:#d9a928;color:#172033;font:600 14px/1.2 Arial,sans-serif;text-decoration:none;box-shadow:0 2px 8px rgba(0,0,0,.18)">🏠 BDC Home</a>

<div class="container py-5" style="max-width:760px">
<div class="card shadow-sm">
<div class="card-body p-4 p-md-5">
<h1 class="h3">BDC Portal Database Setup</h1>
<p class="text-muted">Version 2.2.0</p>

<?php if (is_file($configFile)): ?>
<div class="alert alert-warning">
A configuration file already exists. Saving will replace its database settings.
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<?php if ($message): ?>
<div class="alert alert-success"><?= h($message) ?></div>
<?php endif; ?>

<form method="post" autocomplete="off">
<div class="row">
<div class="col-md-8 mb-3">
<label class="form-label">Database host</label>
<input class="form-control" name="host" value="<?= h((string)$defaults['host']) ?>" required>
<div class="form-text">Use <strong>localhost</strong> for standard Bluehost shared hosting.</div>
</div>
<div class="col-md-4 mb-3">
<label class="form-label">Port</label>
<input class="form-control" type="number" name="port" value="<?= h((string)$defaults['port']) ?>" required>
</div>
</div>

<div class="mb-3">
<label class="form-label">Database name</label>
<input class="form-control" name="database" value="<?= h((string)$defaults['database']) ?>" required>
</div>

<div class="mb-3">
<label class="form-label">Database username</label>
<input class="form-control" name="username" value="<?= h((string)$defaults['username']) ?>" required>
</div>

<div class="mb-3">
<label class="form-label">Database password</label>
<input class="form-control" type="password" name="password" required>
<div class="form-text">Used only to test the connection and create the protected secret file. It is never written into PHP configuration.</div>
</div>

<div class="mb-3">
<label class="form-label">Protected password file</label>
<input class="form-control" name="password_file" value="<?= h((string)$defaults['password_file']) ?>" required>
<div class="form-text">Absolute server path outside the public application folder. The installer creates it with owner-only permissions.</div>
</div>

<div class="mb-4">
<label class="form-label">Portal folder</label>
<div class="input-group">
<span class="input-group-text">/</span>
<input class="form-control" name="base_path" value="<?= h((string)$defaults['base_path']) ?>" required>
</div>
</div>

<div class="d-flex gap-2 flex-wrap">
<button class="btn btn-outline-dark" type="submit" name="test" value="1">Test Connection</button>
<button class="btn btn-dark" type="submit" name="save" value="1">Save and Continue</button>
</div>
</form>

<hr class="my-4">
<p class="small text-muted mb-0">
After installation, delete <code>setup.php</code> and <code>install.php</code>.
</p>
</div>
</div>
</div>
</body>
</html>
