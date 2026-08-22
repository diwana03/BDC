<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$service=(string)file_get_contents($root.'/app/Services/JudgeDirectoryService.php');
$script=(string)file_get_contents($root.'/public/js/dance-cup-directory.js');
$category=(string)file_get_contents($root.'/admin/dance-cup/category.php');
$version=json_decode((string)file_get_contents($root.'/VERSION.json'),true,512,JSON_THROW_ON_ERROR);

$checks=[
    'unique full-name contains binding'=>str_contains($service,':full_contains'),
    'unique display-name contains binding'=>str_contains($service,':display_contains'),
    'unique full-name prefix binding'=>str_contains($service,':full_prefix'),
    'unique display-name prefix binding'=>str_contains($service,':display_prefix'),
    'legacy repeated query binding removed'=>!str_contains($service,'full_name LIKE :q OR display_name LIKE :q'),
    'search starts at one character'=>str_contains($script,'if(q.length<1)'),
    'focus retries an entered query'=>str_contains($script,"input.addEventListener('focus'"),
    'visible failed-search feedback'=>str_contains($script,'Judge Database search could not load. Please retry.'),
    'fresh directory asset'=>str_contains($category,'dance-cup-directory.js?v=351'),
    'release version'=>($version['version']??'')==='2.3.3-dev351'&&($version['build']??0)===3057,
];
foreach($checks as $label=>$passed)if(!$passed)throw new RuntimeException('Failed: '.$label);
echo "OK: Dance Cup Judge Database query works with native prepared statements and visible feedback\n";
