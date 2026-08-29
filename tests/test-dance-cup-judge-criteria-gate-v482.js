const fs=require('fs'),assert=require('assert');
const service=fs.readFileSync('app/Services/DanceCupScoringService.php','utf8');
const judge=fs.readFileSync('admin/dance-cup/judge-scoring.php','utf8');
for(const marker of ['criteria_version CHAR(64)','criteria_accepted_at DATETIME'])assert(service.includes(marker),`Missing session acceptance column: ${marker}`);
for(const marker of ['accept_criteria','Accept &amp; Start Scoring','dc-criteria-gate','criteriaAccepted','criteriaVersion','hash_equals','category_id'])assert(judge.includes(marker),`Missing criteria gate behavior: ${marker}`);
assert(judge.includes("REQUEST_METHOD']==='POST'&&!$criteriaAccepted"),'Server must reject scoring before acceptance');
assert(judge.includes("$test?'bdc_test_dance_cup':'bdc_dance_cup'"),'Test and Live must share the same gate');
assert(!judge.includes('localStorage'),'Acceptance must be server-recorded, not browser-only');
console.log('Dance Cup per-category judge criteria gate v482 passed.');
