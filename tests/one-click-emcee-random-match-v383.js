'use strict';
const fs=require('fs'),assert=require('assert');
for(const path of ['admin/scoring-tests/index.php','admin/scoring/core.php']){const s=fs.readFileSync(path,'utf8');assert(s.includes('name="action" value="randomize"'),'dashboard Emcee button must start randomization');assert(s.includes('method="post" target="_blank"'),'dashboard must POST to Emcee in a new tab');assert(s.includes('final-pairing-sync.js?v=383'));}
const sync=fs.readFileSync('public/js/final-pairing-sync.js','utf8');assert(sync.includes('save.disabled=!complete'));assert(sync.includes('confirmButton.disabled=!complete'));assert(sync.includes("select.value='0'"));
console.log('One-click Emcee Random Match and complete-pair gate parity passed.');
