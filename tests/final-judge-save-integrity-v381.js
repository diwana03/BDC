'use strict';
const fs=require('fs'),assert=require('assert');
const js=fs.readFileSync('public/js/scoring-judge-directory.js','utf8');
assert(js.includes('The same judge cannot be selected more than once'),'client must block duplicate Final judges');
assert(js.includes('bdc-final-judge-scroll:'),'Final judge save must restore panel position');
for(const path of ['admin/scoring-tests/index.php','admin/scoring/core.php']){const s=fs.readFileSync(path,'utf8');assert(s.includes('data-final-judge-status'),'save feedback must remain beside Final panel');assert(s.includes('scoring-judge-directory.js?v=381'));}
const test=fs.readFileSync('admin/scoring-tests/index.php','utf8');
assert(test.includes("ScoringJudgeAssignmentService::save($pdo,$roundId,$rows")&&test.includes("'bdc_test_scoring_judges','bdc_test_scoring_rounds'"),'Testing Final must use shared isolated duplicate-safe assignments');
const service=fs.readFileSync('app/Services/ScoringJudgeAssignmentService.php','utf8');
assert(service.includes('The same judge cannot be selected more than once.'),'server name duplicate gate missing');
assert(service.includes('The same Judge Database profile cannot be assigned twice.'),'server profile duplicate gate missing');
console.log('Final Judge save and duplicate-integrity parity passed.');
