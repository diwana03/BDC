'use strict';
const fs=require('fs'),assert=require('assert');
for(const path of ['admin/scoring-tests/index.php','admin/scoring/core.php']){
 const source=fs.readFileSync(path,'utf8');
 assert(source.includes('data-final-score-state-url='));
 assert(source.includes('data-final-judge-header='));
 assert(source.includes('data-final-score-sync-status'));
 assert(source.includes('final-score-sync.js?v=385'));
 assert(source.lastIndexOf('final-score-sync.js?v=385')<source.lastIndexOf('</body>'));
}
const endpoint=fs.readFileSync('admin/scoring/final-score-state.php','utf8');
for(const table of ['scoring_rounds','scoring_judges','scoring_judge_sessions','scoring_final_marks','scoring_final_results'])assert(endpoint.includes('{$prefix}'+table));
const sync=fs.readFileSync('public/js/final-score-sync.js','utf8');
assert(sync.includes('setInterval(refresh,3000)'));
assert(sync.includes('document.hidden'));
assert(sync.includes("input.dataset.localDirty!=='1'"));
assert(sync.includes('input.readOnly=submitted'));
assert(sync.includes('frame.contentWindow.location.reload()'));
console.log('Automatic Final live score synchronization parity passed.');
