const fs=require('fs');
const assert=require('assert');

const liveDashboard=fs.readFileSync('admin/scoring/core.php','utf8');
const testDashboard=fs.readFileSync('admin/scoring-tests/index.php','utf8');
const liveReport=fs.readFileSync('admin/scoring/result.php','utf8');
const testReport=fs.readFileSync('admin/scoring-tests/result.php','utf8');

for(const [name,source] of [['Live dashboard',liveDashboard],['Testing dashboard',testDashboard]]){
 assert(source.includes("const scoreScrollKey='bdc-score-scroll:'"),name+' must keep round-specific scroll state');
 assert(source.includes("['save_scores','calculate_scores','submit_scores']"),name+' must cover every Manual Heats score action');
 assert(source.includes('rememberScoreScroll(action)'),name+' must save the scorer position before submission');
 assert(source.includes('restoreScoreScroll();'),name+' must restore the scorer position after reload');
 assert(source.includes("sessionStorage.removeItem(scoreScrollKey)"),name+' must consume stale position state');
}

for(const [name,source] of [['Live report',liveReport],['Testing report',testReport]]){
 assert(source.includes("$judgeCount>7||$largestRoleCount>24"),name+' must switch large panels to readable pagination');
 assert(source.includes('array_chunk($judges,14)'),name+' must keep an 11-judge panel together');
 assert(source.includes("elseif($paginateReport)"),name+' must render the paginated report path');
 assert(source.includes('class="paginated-table"'),name+' must use the full-width report table');
 assert(source.includes("['leader'=>'Leaders','follower'=>'Followers']"),name+' must paginate both roles');
}

assert(liveReport.includes("FROM bdc_scoring_entries"),'Live report must retain Live tables');
assert(testReport.includes("FROM bdc_test_scoring_entries"),'Testing report must retain isolated Testing tables');
console.log('Readable large-panel reports and scorer position restore are paired across Testing and Live.');
