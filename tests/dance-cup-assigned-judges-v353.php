<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$category=(string)file_get_contents($root.'/admin/dance-cup/category.php');
$order=(string)file_get_contents($root.'/public/js/dance-cup-judge-order.js');
$css=(string)file_get_contents($root.'/public/css/scoring-premium.css');
$checks=[
    'saved roster renders independently'=>str_contains($category,'data-dc-judge-roster')&&str_contains($category,'Assigned Judges'),
    'judge identity is exposed to ordering'=>str_contains($category,'data-judge-id')&&str_contains($category,'data-chief'),
    'empty state distinguishes missing competitor'=>str_contains($category,'Add at least one competitor to begin scoring.'),
    'ordering reads roster without score table'=>str_contains($order,"document.querySelector('[data-dc-judge-roster]')")&&str_contains($order,'if (!roster && !scoreTable) return;'),
    'premium roster styling is present'=>str_contains($css,'.dc-assigned-judges')&&str_contains($css,'.dc-assigned-judge.is-chief'),
    'test and live prefixes remain shared'=>str_contains($category,"\$test?'bdc_test_dance_cup':'bdc_dance_cup'"),
];
foreach($checks as $label=>$ok){if(!$ok){fwrite(STDERR,"FAIL: {$label}\n");exit(1);}}
echo "Dance Cup assigned-judge static checks passed.\n";
