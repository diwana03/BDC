<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Secret
{
    public static function required(string $environmentKey, ?string $filePath = null): string
    {
        $value = getenv($environmentKey);
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $filePath = trim((string) $filePath);
        if ($filePath !== '') {
            if (!self::isAbsolutePath($filePath) || !is_file($filePath) || !is_readable($filePath)) {
                throw new RuntimeException($environmentKey . ' secret file is missing or unreadable.');
            }
            $value = rtrim((string) file_get_contents($filePath), "\r\n");
            if ($value !== '') {
                return $value;
            }
        }

        throw new RuntimeException(
            'Database password is not configured. Set ' . $environmentKey .
            ' or configure its protected password_file outside the public application directory.'
        );
    }

    private static function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}
