<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$service=(string)file_get_contents($root.'/app/Services/NextRankedFinalistService.php');
$live=(string)file_get_contents($root.'/admin/scoring/core.php');
$test=(string)file_get_contents($root.'/admin/scoring-tests/index.php');

foreach(['Live'=>$live,'Test'=>$test] as $surface=>$source){
    if(!str_contains($source,'NextRankedFinalistService::state'))throw new RuntimeException($surface.' Final does not load promotion state.');
    if(!str_contains($source,'NextRankedFinalistService::promote'))throw new RuntimeException($surface.' Final does not use the shared promotion guard.');
    if(!str_contains($source,'Promote Next Ranked Competitor'))throw new RuntimeException($surface.' Final promotion panel is missing.');
    if(!str_contains($source,'even when role counts are balanced'))throw new RuntimeException($surface.' does not explain optional expansion.');
}
foreach([
    "ORDER BY sr.rank_number ASC,sr.total_score DESC,se.bib_number ASC",
    "NOT EXISTS(SELECT 1 FROM {\$t['entries']} fe",
    "Final scoring has started",
    "DELETE FROM {\$t['pairs']} WHERE round_id=:r",
    "next_ranked_finalist_promoted",
    "'new_role_count'",
] as $required){
    if(!str_contains($service,$required))throw new RuntimeException('Promotion service is missing safeguard: '.$required);
}
if(str_contains($service,'already has the required number'))throw new RuntimeException('Balanced Finals are still blocked from optional promotion.');

echo "Next-ranked Final promotion regression checks passed.\n";
