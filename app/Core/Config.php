<?php
declare(strict_types=1);

namespace App\Core;

final class Config
{
    private static array $data = [];

    public static function load(string $path): void
    {
        $config = require $path;
        if (!is_array($config)) {
            throw new \RuntimeException('Configuration file must return an array.');
        }
        self::$data = $config;
        date_default_timezone_set((string) self::get('app.timezone', 'Asia/Singapore'));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$data;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }
}
