<?php
declare(strict_types=1);
$feed=(string)file_get_contents(__DIR__.'/../live-display/feed.php');
foreach([
 "heading.textContent='Result'"=>'Result heading',
 "rank===1?'Champion'"=>'Champion label',
 "rank===2?'1st Runner-Up'"=>'first runner-up label',
 "rank===3?'2nd Runner-Up'"=>'second runner-up label',
 "replace(/^#(\\d+)/,'P$1')"=>'pair P-number label',
 "textContent.trim().toLowerCase()==='sum'"=>'SUM removal',
] as $needle=>$label){if(!str_contains($feed,$needle)){fwrite(STDERR,"Missing {$label}\n");exit(1);}}
echo "Final matrix pair/result presentation regression passed\n";
