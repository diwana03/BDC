<?php
declare(strict_types=1);

function repository_document_link(array $document, string $portalRoot, string $appUrl): array
{
    $portalRoot = rtrim(str_replace('\\', '/', $portalRoot), '/');
    $appUrl = rtrim($appUrl, '/');
    $storagePath = trim((string) ($document['storage_path'] ?? ''));
    $storedUrl = trim((string) ($document['url'] ?? ''));

    $managedFilename = repository_managed_document_filename($storagePath, $storedUrl);
    $isManaged = $storagePath !== '' || repository_is_bdc_managed_url($storedUrl);

    /*
     * Every BDC-managed repository document is resolved from the protected
     * repository belonging to the CURRENT environment. Historical Production
     * paths/URLs are only used to recover the filename; they are never served
     * directly on Staging (or vice versa).
     */
    if ($isManaged && $managedFilename !== null) {
        try {
            $path = \App\Services\ResultStorageService::resolveFilename($managedFilename);
            return [
                'url' => $path ? \App\Services\ResultStorageService::publicUrl($managedFilename) : null,
                'exists' => $path !== null,
                'local' => true,
                'path' => $path,
            ];
        } catch (\Throwable) {
            return ['url' => null, 'exists' => false, 'local' => true, 'path' => null];
        }
    }

    if ($isManaged) {
        return ['url' => null, 'exists' => false, 'local' => true, 'path' => null];
    }

    /* Genuine third-party/external documents may remain external. */
    if ($storedUrl !== '' && filter_var($storedUrl, FILTER_VALIDATE_URL)) {
        return ['url' => $storedUrl, 'exists' => true, 'local' => false, 'path' => null];
    }

    return ['url' => null, 'exists' => false, 'local' => false, 'path' => null];
}

function repository_managed_document_filename(string $storagePath, string $storedUrl): ?string
{
    if (str_starts_with($storagePath, 'protected-results://')) {
        return repository_safe_result_basename(substr($storagePath, 20));
    }

    if ($storagePath !== '') {
        return repository_safe_result_basename($storagePath);
    }

    if ($storedUrl !== '' && repository_is_bdc_managed_url($storedUrl)) {
        $query = parse_url($storedUrl, PHP_URL_QUERY);
        if (is_string($query) && $query !== '') {
            parse_str($query, $params);
            if (!empty($params['file']) && is_string($params['file'])) {
                $file = repository_safe_result_basename($params['file']);
                if ($file !== null) return $file;
            }
        }
        $path = parse_url($storedUrl, PHP_URL_PATH);
        if (is_string($path) && $path !== '') {
            return repository_safe_result_basename($path);
        }
    }

    return null;
}

function repository_safe_result_basename(string $value): ?string
{
    $name = basename(str_replace('\\', '/', rawurldecode($value)));
    if ($name === '' || $name === '.' || $name === '..' || $name === 'result-file.php') return null;
    return $name;
}

function repository_is_bdc_managed_url(string $url): bool
{
    if ($url === '') return false;

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $normal = strtolower(str_replace('\\', '/', $url));
        return str_contains($normal, '/portal/')
            || str_contains($normal, '/bdc_staging/')
            || str_contains($normal, 'result-file.php');
    }

    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    if ($host !== 'bachatadancecouncil.com' && $host !== 'www.bachatadancecouncil.com') {
        return false;
    }

    $path = strtolower((string) parse_url($url, PHP_URL_PATH));
    return str_contains($path, '/portal/')
        || str_contains($path, '/bdc_staging/')
        || str_contains($path, '/results/')
        || str_contains(strtolower($url), 'result-file.php');
}
