<?php
$screen=file_get_contents(__DIR__.'/../admin/scoring-tests/automatic-screen.php');
$panel=file_get_contents(__DIR__.'/../admin/scoring-tests/automatic-inline.php');
if($screen===false||$panel===false)throw new RuntimeException('Unable to read Automatic Test panel surfaces.');

foreach([
 "panelGateway=screenBase+'?panel=1'"=>'screen-local panel gateway',
 'async function fetchJudgePanel(round)'=>'panel request helper',
 "const gateway=panelGateway+'&round_id='"=>'gateway request before direct fallback',
 "const fallback=panelBase+'?round_id='"=>'direct compatibility fallback',
] as $needle=>$description){
 if(!str_contains($screen,$needle))throw new RuntimeException('Missing '.$description.'.');
}
foreach([
 "!empty(\$automaticInlineGateway)"=>'gateway-aware panel action',
 "\$automaticInlineAction.'?round_id='.\$roundId.'&test_mode=automated'"=>'self-relative refresh endpoint',
] as $needle=>$description){
 if(!str_contains($panel,$needle))throw new RuntimeException('Missing '.$description.'.');
}
echo "Automatic Test panel routing checks passed.\n";
