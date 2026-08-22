<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

$live = file_get_contents($root . '/admin/scoring/core.php');
$test = file_get_contents($root . '/admin/scoring-tests/index.php');
$script = file_get_contents($root . '/public/js/scoring-judge-directory.js');
$endpoint = file_get_contents($root . '/admin/scoring/judge-directory-search.php');

foreach (['Live' => $live, 'Test' => $test] as $label => $surface) {
    if (!str_contains($surface, 'scoring-judge-directory.js?v=360')) {
        $failures[] = $label . ' manual scoring does not load the shared judge search.';
    }
    if (str_contains($surface, 'position-fixed end-0 bottom-0 m-3 shadow')) {
        $failures[] = $label . ' manual scoring still includes the floating backup shortcut.';
    }
    if (!str_contains($surface, 'backup-panel.php')) {
        $failures[] = $label . ' manual scoring lost the complete recovery panel.';
    }
}

foreach ([
    'one-character query' => 'query.length<1',
    'directory endpoint' => "judge-directory-search.php',location.href",
    'dynamic judge rows' => 'MutationObserver(scan)',
    'directory identity' => 'judge_directory_id[]',
] as $check => $needle) {
    if (!str_contains($script, $needle)) {
        $failures[] = 'Shared judge search is missing ' . $check . '.';
    }
}

foreach ([
    'administrator protection' => 'Auth::requireAdmin()',
    'shared Judge Database service' => 'JudgeDirectoryService::search',
    'one-character server search' => 'mb_strlen($term)<1',
] as $check => $needle) {
    if (!str_contains($endpoint, $needle)) {
        $failures[] = 'Directory endpoint is missing ' . $check . '.';
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "J&J judge directory search v360 checks passed.\n";
