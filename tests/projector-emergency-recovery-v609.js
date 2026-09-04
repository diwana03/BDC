const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const feed = fs.readFileSync(path.resolve(__dirname, '../live-display/feed.php'), 'utf8');

assert.doesNotMatch(feed, /projector-roster-v608\.css/);
assert.doesNotMatch(feed, /\$competitorRoleRows=max\(1,\(int\)ceil/);
assert.match(feed, /projector-themes-v352\.css\?v=355/);
assert.match(feed, /class="competitor-card"/);
assert.match(feed, /class="judge-card/);

console.log('Emergency projector renderer recovery checks passed.');
