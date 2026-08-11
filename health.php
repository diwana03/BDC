<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$configPath = __DIR__ . '/config/config.php';
if (!is_file($configPath)) {
    http_response_code(503);
    echo json_encode(['status' => 'unavailable'], JSON_UNESCAPED_SLASHES);
    exit;
}
require __DIR__ . '/bootstrap.php';

$release=[];
$manifest=__DIR__.'/storage/release.json';
if(is_file($manifest)&&is_readable($manifest)){
    $candidate=json_decode((string)file_get_contents($manifest),true);
    if(is_array($candidate))$release=$candidate;
}
if($release===[]){
    $versionFile=__DIR__.'/VERSION.json';
    if(is_file($versionFile)&&is_readable($versionFile)){
        $candidate=json_decode((string)file_get_contents($versionFile),true);
        if(is_array($candidate))$release=$candidate;
    }
}

$status = ['status' => 'unavailable'];
try {
    $pdo = App\Core\Database::connection();
    $pdo->query('SELECT 1');
    $installed=(bool)$pdo->query("SHOW TABLES LIKE 'bdc_users'")->fetchColumn();
    $status=[
        'status'=>$installed?'ok':'unavailable',
        'version'=>(string)($release['version']??''),
        'build'=>$release['build']??null,
        'commit_sha'=>(string)($release['commit_sha']??''),
        'environment'=>(string)($release['environment']??App\Services\ReleaseManagerService::environment()),
    ];
    http_response_code($installed?200:503);
} catch (Throwable $e) {
    $status=[
        'status'=>'unavailable',
        'version'=>(string)($release['version']??''),
        'commit_sha'=>(string)($release['commit_sha']??''),
        'environment'=>(string)($release['environment']??''),
    ];
    http_response_code(500);
}
echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
