const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const feed = fs.readFileSync(path.resolve(__dirname, '../live-display/feed.php'), 'utf8');

assert.match(feed, /class="judge-card<\?=\$isChief\?" chief":""\?>"/);
assert.match(feed, /class="judge-photo-frame"/);
assert.match(feed, /class="judge-photo-fallback">J<\?=\$judgeOrder\?>/);
assert.match(feed, /class="judge-name"/);
assert.match(feed, /class="judge-position"/);
assert.match(feed, /class="judge-scope"/);
assert.match(feed, /class="judge-country"/);
assert.match(feed, /class="judge-flag"/);
assert.match(feed, /\.judge-card\.chief\{/);
assert.match(feed, /\$scopeLabel=\$scope==="leader"\?"Leaders Only"/);
assert.doesNotMatch(feed, /min-height:clamp\(240px,28vh,520px\)/);

console.log('Uniform judge projector card regression checks passed.');
