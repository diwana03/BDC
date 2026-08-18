<?php
$screen=file_get_contents(__DIR__.'/../admin/scoring-tests/automatic-screen.php');
$panel=file_get_contents(__DIR__.'/../admin/scoring-tests/automatic-inline.php');
if($screen===false||$panel===false)throw new RuntimeException('Unable to read Automatic Test panel surfaces.');

foreach([
 "new URL('automatic-inline.php',window.location.href).pathname"=>'same-directory panel fallback',
 'async function fetchJudgePanel(round)'=>'fallback request helper',
 'candidates=[panelFallback,panelBase]'=>'runtime path before configured path',
 "if(response.status!==404)break"=>'404-only fallback guard',
] as $needle=>$description){
 if(!str_contains($screen,$needle))throw new RuntimeException('Missing '.$description.'.');
}
foreach([
 "\$automaticInlineAction='automatic-inline.php'"=>'self-relative panel action',
 "\$automaticInlineAction.'?round_id='.\$roundId.'&test_mode=automated'"=>'self-relative refresh endpoint',
] as $needle=>$description){
 if(!str_contains($panel,$needle))throw new RuntimeException('Missing '.$description.'.');
}
if(str_contains($panel,"url('admin/scoring-tests/automatic-inline.php")){
 throw new RuntimeException('Configured-base Automatic Test self-request remains.');
}

echo "Automatic Test panel routing checks passed.\n";
