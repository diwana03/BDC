const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const feed = fs.readFileSync(path.resolve(__dirname, '../live-display/feed.php'), 'utf8');
const css = fs.readFileSync(path.resolve(__dirname, '../public/css/projector-roster-v608.css'), 'utf8');

assert.match(feed, /\$competitorRoleRows=max\(1,\(int\)ceil\(max\(array_map\('count',\$competitorRoleItems\)\)\/\$competitorRoleCols\)\)/);
assert.match(feed, /competitor-role-grid competitor-role-rows-<\?=\$competitorRoleRows\?>/);
assert.match(css, /\.competitor-role-rows-5 \.competitor-photo-frame/);
assert.match(css, /width: clamp\(54px, 7\.8vh, 86px\)/);
assert.match(css, /\.stage \.competitor-card,\s*\.stage \.judge-card/);
assert.match(css, /\.stage \.judge-photo-frame/);
assert.doesNotMatch(css, /position:\s*absolute/);

console.log('Responsive non-overlapping projector roster layout checks passed.');
