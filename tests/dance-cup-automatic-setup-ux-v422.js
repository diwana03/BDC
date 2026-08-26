const fs = require('fs');
const assert = require('assert');

const setup = fs.readFileSync('admin/dance-cup/automatic-setup.php', 'utf8');
const directory = fs.readFileSync('public/js/dance-cup-directory.js', 'utf8');

assert(setup.includes('data-directory-type="competitor"'), 'Automatic setup must use competitor directory autocomplete');
assert(setup.includes('data-directory-type="judge"'), 'Automatic setup must use judge directory autocomplete');
assert(setup.includes('Live Projection'), 'Automatic setup must expose Live Projection');
assert(setup.includes('projection-control.php?id='), 'Live Projection must route to Dance Cup projection control');
assert(setup.includes('data_mode=test') || setup.includes('$suffix'), 'Projection route must preserve Test/Live mode');

assert(directory.includes('.dc-directory-menu'), 'Directory client must style the dropdown container');
assert(directory.includes('position:absolute'), 'Directory dropdown must be anchored below its field');
assert(directory.includes('max-height'), 'Directory dropdown must constrain long result lists');
assert(directory.includes('overflow-y:auto'), 'Directory dropdown must scroll cleanly');
assert(directory.includes(':hover') || directory.includes(':focus'), 'Directory results must expose interactive highlight styling');
assert(directory.includes('z-index'), 'Directory dropdown must layer above surrounding cards');

console.log('dance-cup-automatic-setup-ux-v422: PASS');
