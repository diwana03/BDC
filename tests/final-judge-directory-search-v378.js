'use strict';
const fs=require('fs'),assert=require('assert');
const js=fs.readFileSync('public/js/scoring-judge-directory.js','utf8');
assert(js.includes('input[name^="final_judges["][name$="[name]"]'),'Final judge names must use shared Judge Database search');
assert(js.includes('input[name$="[directory_id]"]'),'Final selections must retain Judge Directory IDs');
for(const path of ['admin/scoring-tests/index.php','admin/scoring/core.php']){
 const source=fs.readFileSync(path,'utf8');
 assert(/scoring-judge-directory\.js\?v=(?:37[8-9]|38\d|[4-9]\d{2,})/.test(source),path+' must load the repaired search asset');
 assert(source.includes('name="final_judges['),path+' must retain Final judge fields');
}
console.log('Final Judge Database search parity passed.');
