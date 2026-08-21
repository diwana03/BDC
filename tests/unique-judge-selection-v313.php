<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$service=(string)file_get_contents($root.'/app/Services/ScoringJudgeAssignmentService.php');
$automatic=(string)file_get_contents($root.'/admin/scoring/automatic-common-setup.php');
$test=(string)file_get_contents($root.'/admin/scoring-tests/index.php');

foreach([
    "preg_replace('/\\s+/u',' ',",
    'The same judge cannot be selected more than once.',
    'The same Judge Database profile cannot be assigned twice.',
    'postedDirectoryIds',
] as $marker){
    if(!str_contains($service,$marker)){
        fwrite(STDERR,"Backend duplicate-judge protection is incomplete.\n");exit(1);
    }
}
foreach([
    'data-judge-id=',
    'list.replaceChildren()',
    'selected.has(normalise(item.value))',
    'input.setCustomValidity',
    'submit.disabled=duplicate',
] as $marker){
    if(!str_contains($automatic,$marker)){
        fwrite(STDERR,"Automatic judge-search filtering is incomplete.\n");exit(1);
    }
}
if(!str_contains($test,"preg_replace('/\\s+/u',' ',")||!str_contains($test,'The same judge cannot be selected more than once.')){
    fwrite(STDERR,"Test judge duplicate validation does not match Live.\n");exit(1);
}
echo "Unique judge selection checks passed.\n";
