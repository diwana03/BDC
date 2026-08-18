<?php
$source=file_get_contents(__DIR__.'/../judge-scoring/index.php');
if($source===false)throw new RuntimeException('Unable to read Final judge scoring surface.');

foreach([
 'id="rankingReviewOpen"'=>'ranking review trigger',
 'id="rankingReviewDialog"'=>'ranking review dialog',
 'id="rankingReviewLeft"'=>'desktop left ranking table',
 'id="rankingReviewRight"'=>'desktop right ranking table',
 'id="rankingReviewMobile"'=>'mobile ranking table',
 'View My Ranking · '=>'live placement progress label',
 '— NOT SELECTED —'=>'missing-placement state',
 'scrollIntoView({behavior:\'smooth\',block:\'center\'})'=>'rank-to-couple navigation',
 'and change \'+duplicate.dataset.label+\' to NO RANK?'=>'duplicate-rank replacement confirmation',
 'duplicate.value=\'\';updateFinalRankButtons();await persistFinalRank(duplicate)'=>'previous couple NO RANK persistence',
] as $needle=>$description){
 if(!str_contains($source,$needle))throw new RuntimeException('Missing '.$description.'.');
}

if(!str_contains($source,'@media(max-width:620px)')||!str_contains($source,'.ranking-review-grid{display:none}')){
 throw new RuntimeException('Mobile single-table ranking review layout is missing.');
}

echo "Final judge ranking review checks passed.\n";
