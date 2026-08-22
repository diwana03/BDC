<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$feed = file_get_contents($root . '/live-display/feed.php');
$matrix = file_get_contents($root . '/live-display/final-relative-placement.php');
$index = file_get_contents($root . '/live-display/index.php');
$css = file_get_contents($root . '/public/css/projector-themes-v352.css');

$checks = [
    'feed refreshes projector theme cache' => str_contains($feed, 'projector-themes-v352.css?v=355'),
    'holding logo is centered with title' => str_contains($feed, 'holding-inner') && str_contains($feed, 'holding-logo') && str_contains($feed, 'holding-title'),
    'holding logo has projector scale' => str_contains($feed, 'width:clamp(104px,10vw,190px)'),
    'feed injects integrated logo' => str_contains($feed, 'projection-brand') && str_contains($feed, 'bdc-logo.png'),
    'feed supplies official identity' => str_contains($feed, 'Official Live Display'),
    'final matrix uses shared branding' => str_contains($matrix, 'projector-themes-v352.css?v=355') && str_contains($matrix, 'projection-brand'),
    'projector shell refreshes theme cache' => str_contains($index, 'projector-themes-v352.css?v=355'),
    'all four themes remain available' => str_contains($css, 'obsidian_gold') && str_contains($css, 'ivory_burgundy') && str_contains($css, 'pearl_sapphire'),
    'theme-aware text is defined' => str_contains($css, '--bdc-ink') && str_contains($css, '--bdc-muted'),
    'responsive logo uses container units' => str_contains($css, 'cqw') && str_contains($css, 'cqh'),
    'projector shell skips directory and index routes' => str_contains(file_get_contents($root . '/public/js/bdc-global-branding.js'), '/\\/live-display(?:\\/index\\.php)?\\/?$/'),
    'projector feed keeps native logo sizing' => str_contains(file_get_contents($root . '/public/js/bdc-global-branding.js'), "closest('.projection-brand, .holding-inner')"),
    'venue logo is presentation scale' => str_contains($css, 'width:max(88px,min(8cqw,14cqh))'),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}

echo "Projector branding/theme static checks passed.\n";
