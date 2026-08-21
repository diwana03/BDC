<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$branding = file_get_contents($root . '/public/js/bdc-global-branding.js');
$theme = file_get_contents($root . '/public/assets/js/bdc-theme.js');
$bootstrap = file_get_contents($root . '/bootstrap.php');

$checks = [
    'global premium navbar class' => str_contains($branding, 'bdc-premium-navbar'),
    'official logo remains white tile' => str_contains($branding, 'background:#fff!important'),
    'contextual identity' => str_contains($branding, 'contextLabel()'),
    'responsive navigation actions' => str_contains($branding, 'bdc-premium-nav-actions'),
    'theme control docks into navbar' => str_contains($branding, 'function dockThemeControl()'),
    'theme navbar control treatment' => str_contains($branding, '.bdc-theme-control-navbar'),
    'light remains first-time default' => str_contains($theme, "saved : 'light'") && str_contains($theme, "value : 'light'"),
    'branding cache version bumped' => str_contains($bootstrap, 'bdc-global-branding.js?v=326'),
    'shared branding cache version bumped' => str_contains($bootstrap, 'bdc-global-branding.js?v=326'),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failed) {
    fwrite(STDERR, "Premium header v326 failed: " . implode(', ', $failed) . PHP_EOL);
    exit(1);
}

echo "Premium header v326 checks passed." . PHP_EOL;
