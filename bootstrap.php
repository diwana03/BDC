<?php
declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$configPath = __DIR__ . '/config/config.php';
if (!is_file($configPath)) {
    http_response_code(503);
    exit(
        'Portal configuration is not complete. Open <a href="./setup.php">setup.php</a>.'
    );
}

\App\Core\Config::load($configPath);

session_name((string) \App\Core\Config::get('app.session_name', 'bdc_portal_session'));
session_set_cookie_params([
    'httponly' => true,
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'samesite' => 'Lax',
    'path' => (string) \App\Core\Config::get('app.base_path', '/portal'),
]);
session_start();

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    $base = rtrim((string) \App\Core\Config::get('app.base_path', '/portal'), '/');
    return $base . '/' . ltrim($path, '/');
}
