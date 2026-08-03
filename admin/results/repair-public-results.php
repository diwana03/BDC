<?php
declare(strict_types=1);

require dirname(__DIR__,2).'/bootstrap.php';

use App\Core\Auth;

Auth::requireSuperAdmin();

$root=dirname(__DIR__,2).'/public/results';
$fixed=0;
$failed=0;

if(!is_dir($root)){
    if(!mkdir($root,0755,true) && !is_dir($root)){
        http_response_code(500);
        exit('Could not create public/results.');
    }
}

@chmod($root,0755);

foreach(glob($root.'/*.html')?:[] as $file){
    if(!is_file($file))continue;

    if(@chmod($file,0644)){
        $fixed++;
    }else{
        $failed++;
    }
}

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Repair Public Result Permissions</title>
<style>
body{font-family:Arial,sans-serif;background:#f5f6f8;padding:40px}
.card{max-width:720px;margin:auto;background:#fff;padding:28px;border-radius:12px;box-shadow:0 8px 24px #0001}
.ok{color:#087830}.bad{color:#b42318}
a{display:inline-block;margin-top:18px}
</style>
</head>
<body>
<div class="card">
<h1 class="<?=$failed===0?'ok':'bad'?>">Public Result Permission Repair</h1>
<p><strong>Folder:</strong> public/results → 0755</p>
<p><strong>HTML files repaired:</strong> <?=$fixed?></p>
<p><strong>Failed:</strong> <?=$failed?></p>
<p>All readable result files are now set to <strong>0644</strong>.</p>
<a href="index.php">Return to Result Repository</a>
</div>
</body>
</html>
