<?php
declare(strict_types=1);

$root=__DIR__;
$dir=$root.'/storage/patch-backups';

function restoreV227(string $pattern,string $target):string{
 $files=glob($pattern)?:[];
 rsort($files);
 if(!$files)return 'backup not found';
 return copy($files[0],$target)?'restored from '.$files[0]:'restore failed';
}

echo '<h1>BDC v2.0.27 rollback</h1>';
echo '<p>Publish page: '.htmlspecialchars(restoreV227($dir.'/publish-before-v227-*.php',$root.'/admin/scoring/publish.php')).'</p>';
echo '<p>Scoring dashboard: '.htmlspecialchars(restoreV227($dir.'/scoring-index-before-v227-*.php',$root.'/admin/scoring/index.php')).'</p>';
