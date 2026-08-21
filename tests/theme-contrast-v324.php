<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$theme = file_get_contents($root . '/public/assets/css/bdc-theme.css');
$controller = file_get_contents($root . '/public/assets/js/bdc-theme.js');
$live = file_get_contents($root . '/judge-scoring/index.php');
$test = file_get_contents($root . '/test-judge-scoring/index.php');
if (!$theme || !$controller || !$live || !$test) throw new RuntimeException('Theme sources are missing.');

$checks = [
    'Light default' => str_contains($controller, "saved : 'light'") && str_contains($controller, "value : 'light'"),
    'layered dark surfaces' => str_contains($theme, '--bdc-theme-surface-3:#202b3d'),
    'dark instructions' => str_contains($theme, '.alert.success') && str_contains($theme, '.alert.warning'),
    'dark Review Later' => str_contains($theme, '.review-list{background:#30270f'),
    'dark choice states' => str_contains($theme, '.choice[data-value="YES"]') && str_contains($theme, '.choice.no.active'),
    'Live subtitle filter' => str_contains($live, '<div class="eyebrow">BDC AUTOMATIC SCORING</div>'),
    'Test subtitle filter' => str_contains($test, '<span class="navbar-brand">BDC Automatic Scoring TEST</span>'),
];
foreach ($checks as $label => $passed) if (!$passed) throw new RuntimeException('Failed: ' . $label);
echo "OK: premium Dark theme, Light default and judge branding v324\n";
