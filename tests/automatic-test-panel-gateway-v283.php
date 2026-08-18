<?php
declare(strict_types=1);
$screen=(string)file_get_contents(__DIR__.'/../admin/scoring-tests/automatic-screen.php');
$panel=(string)file_get_contents(__DIR__.'/../admin/scoring-tests/automatic-inline.php');
$checks=[
 "(\$_GET['panel'] ?? '') === '1'"=>'screen-local panel gateway',
 "require __DIR__ . '/automatic-inline.php'"=>'gateway includes shared panel',
 "panelGateway=screenBase+'?panel=1'"=>'client uses known-working screen endpoint first',
 "const detail=(await response.text())"=>'HTTP failure exposes useful response detail',
];
foreach($checks as $needle=>$label){if(!str_contains($screen,$needle)){fwrite(STDERR,"Missing {$label}\n");exit(1);}}
if(!str_contains($panel,'!empty($automaticInlineGateway)')){fwrite(STDERR,"Panel forms do not use gateway when embedded\n");exit(1);}
echo "automatic test panel gateway regression passed\n";
