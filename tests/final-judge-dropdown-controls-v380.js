'use strict';
const fs=require('fs'),assert=require('assert');
const search=fs.readFileSync('public/js/scoring-judge-directory.js','utf8');
const order=fs.readFileSync('public/js/judge-order-controls.js','utf8');
assert(search.includes('bdc-judge-search-card-open'),'search card must rise above following cards');
assert(search.includes('overflow:visible!important'),'search dropdown must not be clipped');
assert(order.includes('existingRemove'),'order controls must reuse the existing Final Remove button');
assert(!order.includes('data-remove-judge aria-label="Remove judge">Remove</button>'),'order controls must not hard-code a second Remove button');
for(const path of ['admin/scoring-tests/index.php','admin/scoring/core.php']){const source=fs.readFileSync(path,'utf8');assert(/scoring-judge-directory\.js\?v=(?:38\d|[4-9]\d{2,})/.test(source));assert(/judge-order-controls\.js\?v=(?:38\d|[4-9]\d{2,})/.test(source));}
console.log('Final Judge dropdown overlay and single Remove control parity passed.');
