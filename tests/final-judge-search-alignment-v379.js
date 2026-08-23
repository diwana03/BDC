'use strict';
const fs=require('fs'),assert=require('assert');
const js=fs.readFileSync('public/js/scoring-judge-directory.js','utf8');
assert(js.includes('.input-group>.bdc-judge-search{flex:1 1 auto;width:1%;min-width:0}'),'Final autocomplete wrapper must retain input-group alignment');
assert(js.includes('input[name^="final_judges["][name$="[name]"]'),'Final Judge Database search must remain enabled');
for(const path of ['admin/scoring-tests/index.php','admin/scoring/core.php'])assert(fs.readFileSync(path,'utf8').includes('scoring-judge-directory.js?v=379'),path+' must load aligned search asset');
console.log('Final Judge search alignment parity passed.');
