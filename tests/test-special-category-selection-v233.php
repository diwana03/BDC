<?php
declare(strict_types=1);

$source=file_get_contents(__DIR__.'/../admin/scoring-tests/index.php');
if($source===false){fwrite(STDERR,"Unable to read Test scoring dashboard.\n");exit(1);}
if(!str_contains($source,'use App\\Services\\SpecialCategoryService;')){
 fwrite(STDERR,"Test scoring dashboard does not import SpecialCategoryService.\n");exit(1);
}

$start=strpos($source,"elseif(\$action==='generate_test_event')");
$end=$start===false?false:strpos($source,"elseif(\$action==='generate_test_competitors')",$start);
if($start===false||$end===false){fwrite(STDERR,"Generate Test Event handler was not found.\n");exit(1);}
$handler=substr($source,$start,$end-$start);

$required=[
 "SpecialCategoryService::isSpecial(\$division)",
 "throw new RuntimeException('Invalid division.')",
];
foreach($required as $needle){
 if(!str_contains($handler,$needle)){fwrite(STDERR,"Missing special-category safeguard: {$needle}\n");exit(1);}
}
if(str_contains($handler,"\$division='novice'")){
 fwrite(STDERR,"Generate Test Event still silently converts a selected category to Novice.\n");exit(1);
}

echo "Test special-category selection regression checks passed.\n";

$liveIndex=file_get_contents(__DIR__.'/../admin/scoring/index.php');
$liveCreator=file_get_contents(__DIR__.'/../admin/scoring/integrated-special-create.php');
if($liveIndex===false||$liveCreator===false){fwrite(STDERR,"Unable to read Live special-category creation routes.\n");exit(1);}
foreach([
 "['bachata_rising','bachata_open','bachata_invitational','salsa_rising','salsa_open']",
 "require __DIR__.'/integrated-special-create.php'",
] as $needle){
 if(!str_contains($liveIndex,$needle)){fwrite(STDERR,"Live special-category routing safeguard is missing: {$needle}\n");exit(1);}
}
foreach([
 "SpecialCategoryService::isSpecial(\$category)",
 "'division'=>\$category",
] as $needle){
 if(!str_contains($liveCreator,$needle)){fwrite(STDERR,"Live special-category persistence safeguard is missing: {$needle}\n");exit(1);}
}

echo "Live special-category selection parity checks passed.\n";
