<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Services/ProjectionNameService.php';

use App\Services\ProjectionNameService;

$rows = ProjectionNameService::abbreviateRows([
    ['display_name' => 'Ashish Diwan'],
    ['display_name' => 'Ashish Sharma'],
    ['display_name' => 'Cecilia Koh'],
    ['display_name' => 'Madonna'],
], ['display_name']);

$expected = ['Ashish D', 'Ashish S', 'Cecilia', 'Madonna'];
$actual = array_column($rows, 'display_name');
if ($actual !== $expected) {
    fwrite(STDERR, 'Projection name abbreviation failed: ' . json_encode($actual) . PHP_EOL);
    exit(1);
}

$pairs = ProjectionNameService::abbreviateRows([
    ['leader_name' => 'Alex Tan', 'follower_name' => 'Alex Wong'],
], ['leader_name', 'follower_name']);
if ($pairs[0]['leader_name'] !== 'Alex T' || $pairs[0]['follower_name'] !== 'Alex W') {
    fwrite(STDERR, "Cross-role duplicate detection failed.\n");
    exit(1);
}

echo "Projection first-name checks passed.\n";
