<?php
declare(strict_types=1);
$screen=(string)file_get_contents(__DIR__.'/../admin/scoring-tests/automatic-screen.php');
foreach([
 "liveScoringBase="=>'Live scoring route configuration',
 'iframe[title="Automatic Final Judge Links"]'=>'Live Final surface detection',
 'iframe[src*="judge_panel=1"]'=>'Live gateway surface detection',
 "window.top.location.replace(liveScoringBase+'?mode=automated&round_id='+round)"=>'Test-to-Live handoff',
] as $needle=>$label){if(!str_contains($screen,$needle)){fwrite(STDERR,"Missing {$label}\n");exit(1);}}
echo "automatic Test-to-Live handoff regression passed\n";
