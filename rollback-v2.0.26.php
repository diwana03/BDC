<?php
declare(strict_types=1);

$root = __DIR__;
$target = $root . '/admin/scoring/index.php';
$backupDir = $root . '/storage/patch-backups';

$backups = glob($backupDir . '/scoring-index-before-v226-*.php') ?: [];
rsort($backups);

if (!$backups) {
    http_response_code(404);
    exit('No v2.0.26 backup was found.');
}

if (!copy($backups[0], $target)) {
    http_response_code(500);
    exit('Unable to restore the backup.');
}

echo 'BDC v2.0.26 rolled back successfully.';
