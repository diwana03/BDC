<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'public/css/scoring-premium.css',
    'public/assets/js/bdc-theme.js',
    'judge-scoring/index.php',
    'test-judge-scoring/index.php',
    'app/Views/admin/dashboard.php',
    'admin/live-screen/projection-workspace.php',
    'admin/live-screen/control.php',
    'admin/dance-cup/category.php',
    'admin/dance-cup/index.php',
    'admin/scoring-tests/automatic-screen.php',
    'admin/scoring/index.php',
    'admin/scoring-tests/select-mode.php',
    'admin/scoring-tests/index.php',
];
foreach ($paths as $path) {
    $source = file_get_contents($root . '/' . $path);
    if (!$source || !str_contains($source, 'bdc-theme.') || !str_contains($source, 'v=325')) {
        throw new RuntimeException('Theme cache version missing: ' . $path);
    }
    if (str_contains($source, 'bdc-theme.js?v=323') || str_contains($source, 'bdc-theme.css?v=323')) {
        throw new RuntimeException('Stale theme cache version: ' . $path);
    }
}
echo "OK: every shared theme entry point uses cache version 325\n";
