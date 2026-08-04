<?php
declare(strict_types=1);

function repository_document_link(array $document, string $portalRoot, string $appUrl): array
{
    $portalRoot = rtrim(str_replace('\\', '/', $portalRoot), '/');
    $appUrl = rtrim($appUrl, '/');
    $storagePath = trim((string) ($document['storage_path'] ?? ''));
    $storedUrl = trim((string) ($document['url'] ?? ''));

    if ($storagePath !== '') {
        if (filter_var($storagePath, FILTER_VALIDATE_URL)) {
            return ['url' => $storagePath, 'exists' => true, 'local' => false, 'path' => null];
        }

        $normalised = str_replace('\\', '/', $storagePath);
        $candidates = [];
        if (preg_match('~^(?:[A-Za-z]:/|/)~', $normalised)) {
            $candidates[] = $normalised;
        }

        $relative = ltrim($normalised, '/');
        $candidates[] = $portalRoot . '/' . $relative;
        if (str_starts_with($relative, 'portal/')) {
            $candidates[] = $portalRoot . '/' . substr($relative, 7);
        }

        foreach (array_unique($candidates) as $candidate) {
            if (!is_file($candidate)) {
                continue;
            }

            $realPortal = realpath($portalRoot);
            $realFile = realpath($candidate);
            if ($realPortal && $realFile) {
                $realPortal = str_replace('\\', '/', $realPortal);
                $realFile = str_replace('\\', '/', $realFile);
                if ($realFile === $realPortal || str_starts_with($realFile, $realPortal . '/')) {
                    $webRelative = ltrim(substr($realFile, strlen($realPortal)), '/');
                    return ['url' => $appUrl . '/' . $webRelative, 'exists' => true, 'local' => true, 'path' => $realFile];
                }
            }

            if ($storedUrl !== '' && filter_var($storedUrl, FILTER_VALIDATE_URL)) {
                return ['url' => $storedUrl, 'exists' => true, 'local' => true, 'path' => $realFile ?: $candidate];
            }
        }

        return ['url' => null, 'exists' => false, 'local' => true, 'path' => null];
    }

    if ($storedUrl !== '' && filter_var($storedUrl, FILTER_VALIDATE_URL)) {
        return ['url' => $storedUrl, 'exists' => true, 'local' => false, 'path' => null];
    }

    return ['url' => null, 'exists' => false, 'local' => false, 'path' => null];
}
