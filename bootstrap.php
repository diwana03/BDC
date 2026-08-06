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

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

session_name((string) \App\Core\Config::get('app.session_name', 'bdc_portal_session'));
session_set_cookie_params([
    'httponly' => true,
    'secure' => (bool) \App\Core\Config::get('security.secure_cookies', true),
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

function country_flag_url(?string $country): ?string
{
    $country = trim((string) $country);
    if ($country === '') return null;
    $aliases = ['usa'=>'us','united states of america'=>'us','uk'=>'gb','united kingdom'=>'gb','south korea'=>'kr','korea'=>'kr','north korea'=>'kp','russia'=>'ru','mainland china'=>'cn','china mainland'=>'cn','hong kong'=>'hk','taiwan'=>'tw','uae'=>'ae','vietnam'=>'vn','viet nam'=>'vn','czech republic'=>'cz'];
    $key = mb_strtolower($country);
    $code = $aliases[$key] ?? null;
    if ($code === null && preg_match('/^[a-z]{2}$/i', $country)) $code = strtolower($country);
    static $countries = null;
    if ($code === null) {
        if ($countries === null) {
            $countries = [];
            $json = @file_get_contents(__DIR__ . '/public/assets/flags/countries.json');
            foreach ((json_decode((string) $json, true) ?: []) as $item) {
                if (!empty($item['name']) && !empty($item['code'])) $countries[mb_strtolower((string) $item['name'])] = strtolower((string) $item['code']);
            }
        }
        $code = $countries[$key] ?? null;
    }
    if ($code === null || !is_file(__DIR__ . '/public/assets/flags/' . $code . '.svg')) return null;
    return url('public/assets/flags/' . $code . '.svg');
}
