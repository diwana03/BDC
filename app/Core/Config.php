<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Config
{
    /** @var array<string, mixed> */
    private static array $values = [];

    public static function load(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Configuration file is missing or unreadable.');
        }

        $values = require $path;
        if (!is_array($values)) {
            throw new RuntimeException('Configuration file must return an array.');
        }

        self::$values = $values;

        $timezone = self::get('app.timezone', 'Asia/Singapore');
        if (!is_string($timezone) || !date_default_timezone_set($timezone)) {
            throw new RuntimeException('Configured application timezone is invalid.');
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if ($key === '') {
            return self::$values;
        }

        $value = self::$values;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
