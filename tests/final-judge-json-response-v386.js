'use strict';
const fs=require('fs'),assert=require('assert');
const live=fs.readFileSync('judge-scoring/index.php','utf8');
assert(live.includes("while(ob_get_level()>0)ob_end_clean()"));
assert(live.includes("if(r.status===429)"));
assert(live.includes("Score save response was interrupted."));
assert(live.includes("This judge link was refreshed or expired."));
const testGateway=fs.readFileSync('test-judge-scoring/index.php','utf8');
assert(testGateway.includes("This test judge link was refreshed or expired."));
assert(testGateway.includes("Content-Type: application/json"));
const test=fs.readFileSync('test-judge-scoring/final.php','utf8');
assert(test.includes('function testFinalJudgeJson'));
assert(test.includes("while(ob_get_level()>0)ob_end_clean()"));
assert(test.includes("if(r.status===429)"));
for(const path of ['admin/scoring/core.php','admin/scoring-tests/index.php']){
 const source=fs.readFileSync(path,'utf8');
 assert(source.includes('final-pairing-sync.js?v=386'));
 assert(source.includes('final-score-sync.js?v=386'));
}
const pairing=fs.readFileSync('public/js/final-pairing-sync.js','utf8');
assert(pairing.includes('busy||settled'));
assert(pairing.includes('settled=complete'));
const score=fs.readFileSync('public/js/final-score-sync.js','utf8');
assert(score.includes('setInterval(refresh,5000)'));
assert(score.includes('response.status===429'));
console.log('Final judge clean JSON and request-throttle parity passed.');

for(const path of ['app/Services/AutomaticJudgeBrowserService.php','app/Services/TestAutomaticJudgeService.php']){
 const source=fs.readFileSync(path,'utf8');assert(source.includes('INTERVAL 72 HOUR'));assert(!source.includes('INTERVAL 12 HOUR'));
}
