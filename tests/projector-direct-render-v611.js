const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const feed = fs.readFileSync(path.resolve(__dirname, '../live-display/feed.php'), 'utf8');

assert.doesNotMatch(feed, /ob_start\(static fn\(string \$html\)/);
assert.match(feed, /if \(\s*\$type === "holding"/);
assert.match(feed, /class="holding"/);
assert.match(feed, /class="competitor-card"/);
assert.match(feed, /class="judge-card/);

console.log('Direct projector rendering regression checks passed.');
