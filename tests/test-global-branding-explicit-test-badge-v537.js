const fs = require('fs');
const assert = require('assert');

const source = fs.readFileSync('public/js/bdc-global-branding.js', 'utf8');

assert.match(source, /querySelectorAll\('\.badge\.text-bg-warning'\)/);
assert.match(source, /\\bTEST ONLY\\b/);
assert.doesNotMatch(source, /const testBadge = brandTarget\.closest\('\.navbar'\)\?\.querySelector\('\.badge\.text-bg-warning'\)/);

console.log('PASS global branding requires an explicit Test badge');
