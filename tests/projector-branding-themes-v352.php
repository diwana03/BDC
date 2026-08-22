<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$feed = file_get_contents($root . '/live-display/feed.php');
$matrix = file_get_contents($root . '/live-display/final-relative-placement.php');
$index = file_get_contents($root . '/live-display/index.php');
$css = file_get_contents($root . '/public/css/projector-themes-v352.css');

$checks = [
    'feed uses v352 theme' => str_contains($feed, 'projector-themes-v352.css?v=352'),
    'feed injects integrated logo' => str_contains($feed, 'projection-brand') && str_contains($feed, 'bdc-logo.png'),
    'feed supplies official identity' => str_contains($feed, 'Official Live Display'),
    'final matrix uses shared branding' => str_contains($matrix, 'projector-themes-v352.css?v=352') && str_contains($matrix, 'projection-brand'),
    'projector shell injects v352 theme' => str_contains($index, 'projector-themes-v352.css?v=352'),
    'all four themes remain available' => str_contains($css, 'obsidian_gold') && str_contains($css, 'ivory_burgundy') && str_contains($css, 'pearl_sapphire'),
    'theme-aware text is defined' => str_contains($css, '--bdc-ink') && str_contains($css, '--bdc-muted'),
    'responsive logo uses container units' => str_contains($css, 'cqw') && str_contains($css, 'cqh'),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}

echo "Projector branding/theme static checks passed.\n";
