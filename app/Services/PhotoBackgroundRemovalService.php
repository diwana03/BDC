<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use RuntimeException;

final class PhotoBackgroundRemovalService
{
    private const ENDPOINT = 'https://api.remove.bg/v1.0/removebg';
    private const MAX_SOURCE_BYTES = 10_485_760;
    private const MAX_RESULT_BYTES = 15_728_640;

    public static function configured(): bool
    {
        return self::apiKey() !== '';
    }

    public static function secretFilePath(): string
    {
        $configured = trim((string) Config::get('integration.remove_bg_api_key_file', ''));
        if ($configured !== '') return $configured;
        $databaseSecret = trim((string) Config::get('database.password_file', ''));
        $directory = $databaseSecret !== '' ? dirname($databaseSecret) : dirname(__DIR__, 2) . '/storage';
        return rtrim($directory, '/') . '/remove-bg-api-key';
    }

    public static function remove(string $photoUrl): string
    {
        $key = self::apiKey();
        if ($key === '') {
            throw new RuntimeException('Background removal is not configured. Add BDC_REMOVE_BG_API_KEY or the protected remove-bg-api-key file.');
        }
        if (!function_exists('curl_init') || !function_exists('curl_file_create')) {
            throw new RuntimeException('Background removal requires the PHP cURL extension.');
        }

        $source = self::localPhotoPath($photoUrl);
        $size = filesize($source);
        if ($size === false || $size < 1 || $size > self::MAX_SOURCE_BYTES) {
            throw new RuntimeException('The source photo must be between 1 byte and 10 MB.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($source);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new RuntimeException('Background removal supports JPG, PNG and WebP source photos.');
        }

        $curl = curl_init(self::ENDPOINT);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => ['X-Api-Key: ' . $key, 'Accept: image/png'],
            CURLOPT_POSTFIELDS => [
                'image_file' => curl_file_create($source, $mime, basename($source)),
                'size' => 'auto',
                'format' => 'png',
            ],
        ]);
        $result = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $responseType = strtolower((string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE));
        $curlError = curl_error($curl);
        curl_close($curl);

        if (!is_string($result) || $result === '' || $status !== 200) {
            $message = self::apiError(is_string($result) ? $result : '');
            throw new RuntimeException($message !== '' ? $message : ('Background removal failed' . ($status ? ' (HTTP ' . $status . ')' : '') . ($curlError !== '' ? ': ' . $curlError : '.')));
        }
        if (!str_contains($responseType, 'image/png') || strlen($result) > self::MAX_RESULT_BYTES || !str_starts_with($result, "\x89PNG\r\n\x1a\n")) {
            throw new RuntimeException('Background removal returned an invalid or oversized image.');
        }
        return $result;
    }

    private static function apiKey(): string
    {
        $environment = trim((string) getenv('BDC_REMOVE_BG_API_KEY'));
        if ($environment !== '') return $environment;
        $path = self::secretFilePath();
        if (!is_file($path) || !is_readable($path)) return '';
        return trim((string) file_get_contents($path));
    }

    private static function localPhotoPath(string $photoUrl): string
    {
        $path = (string) (parse_url($photoUrl, PHP_URL_PATH) ?: $photoUrl);
        $uploadPosition = strpos($path, '/uploads/');
        if ($uploadPosition === false) {
            throw new RuntimeException('Only locally stored portal photos can be processed.');
        }
        $relative = ltrim(substr($path, $uploadPosition + 1), '/');
        $root = realpath(dirname(__DIR__, 2));
        $candidate = realpath(dirname(__DIR__, 2) . '/' . $relative);
        if ($root === false || $candidate === false || !str_starts_with($candidate, $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR) || !is_file($candidate)) {
            throw new RuntimeException('The source photo file is unavailable.');
        }
        return $candidate;
    }

    private static function apiError(string $body): string
    {
        $payload = json_decode($body, true);
        $errors = is_array($payload) ? ($payload['errors'] ?? []) : [];
        if (!is_array($errors) || !$errors) return '';
        $first = $errors[0] ?? [];
        $title = is_array($first) ? trim((string) ($first['title'] ?? '')) : '';
        return $title !== '' ? 'Background removal failed: ' . $title : '';
    }
}
