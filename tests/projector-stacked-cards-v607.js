const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const feed = fs.readFileSync(path.resolve(__dirname, '../live-display/feed.php'), 'utf8');

assert.match(feed, /\.competitor-card\{display:flex;flex-direction:column;align-items:center;justify-content:center/);
assert.match(feed, /\.competitor-photo-frame\{flex:0 0 auto;width:clamp\(46px,6\.2vh,68px\);height:clamp\(46px,6\.2vh,68px\)/);
assert.match(feed, /\.competitor-name\{flex:0 0 auto;width:100%/);
assert.match(feed, /\.competitor-bib\{flex:0 0 auto/);
assert.match(feed, /\.competitor-country\{flex:0 0 auto/);
assert.match(feed, /competitor-role-rows-<\?=\$competitorRoleRows\?>/);
assert.match(feed, /projector-roster-v608\.css\?v=608/);
assert.doesNotMatch(feed, /\.competitor-card\{display:grid;grid-template-columns/);
assert.doesNotMatch(feed, /\.competitor-photo-frame\{grid-column/);

console.log('Stacked competitor projector card regression checks passed.');
