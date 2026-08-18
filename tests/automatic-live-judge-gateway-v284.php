<?php
declare(strict_types=1);
$index=(string)file_get_contents(__DIR__.'/../admin/scoring/index.php');
$setup=(string)file_get_contents(__DIR__.'/../admin/scoring/automatic-common-setup.php');
$core=(string)file_get_contents(__DIR__.'/../admin/scoring/core.php');
foreach([
 [$index,"(\$_GET['judge_panel']??'')==='1'",'Live Automatic gateway'],
 [$index,"require __DIR__.'/judge-control.php'",'shared Live judge control include'],
 [$setup,'index.php?mode=automated&amp;judge_panel=1&amp;round_id=','Live Heats/Semifinal iframe gateway'],
 [$core,'index.php?mode=automated&amp;judge_panel=1&amp;round_id=','Live Final iframe gateway'],
] as [$source,$needle,$label]){if(!str_contains($source,$needle)){fwrite(STDERR,"Missing {$label}\n");exit(1);}}
echo "automatic Live judge gateway regression passed\n";
