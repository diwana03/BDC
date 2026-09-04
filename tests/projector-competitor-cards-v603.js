const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const feed = fs.readFileSync(path.resolve(__dirname, '../live-display/feed.php'), 'utf8');

assert.match(feed, /class="competitor-card"/);
assert.match(feed, /class="competitor-photo-frame"/);
assert.match(feed, /class="competitor-name"/);
assert.match(feed, /class="competitor-bib"/);
assert.match(feed, /class="competitor-country"/);
assert.match(feed, /class="competitor-flag"/);
assert.match(feed, /background:linear-gradient\(155deg,rgba\(31,43,65,\.96\),rgba\(12,20,35,\.98\)\)/);
assert.match(feed, /object-position:center 24%/);
assert.match(feed, /white-space:nowrap;overflow:hidden;text-overflow:ellipsis/);
assert.doesNotMatch(feed, /style="width:clamp\(24px,2\.1vw,48px\)/);
assert.match(feed, /ProjectionLayoutService::balancedPageSlice/);

console.log('Uniform competitor projector card regression checks passed.');
