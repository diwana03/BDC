const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const feed = fs.readFileSync(path.resolve(__dirname, '../live-display/feed.php'), 'utf8');

assert.match(feed, /\.competitor-card\{display:grid;grid-template-columns:clamp\(48px,4\.1vw,82px\) minmax\(0,1fr\);grid-template-rows:auto auto auto/);
assert.match(feed, /\.competitor-photo-frame\{grid-column:1;grid-row:1\/4;width:clamp\(48px,4\.1vw,82px\);height:clamp\(48px,4\.1vw,82px\)/);
assert.match(feed, /\.competitor-card \.photo\{display:block;width:100%;height:100%/);
assert.match(feed, /\.competitor-name\{grid-column:2;grid-row:1/);
assert.match(feed, /\.competitor-bib\{grid-column:2;grid-row:2/);
assert.match(feed, /\.competitor-country\{grid-column:2;grid-row:3/);
assert.match(feed, /\.judge-photo-frame\{position:relative;align-self:center;width:clamp\(64px,min\(8vw,13vh\),170px\);height:clamp\(64px,min\(8vw,13vh\),170px\)/);
assert.doesNotMatch(feed, /\.competitor-card\{container-type:size/);
assert.doesNotMatch(feed, /\.competitor-card \.photo\{[^}]*max-height:100%/);

console.log('Fixed horizontal projector card regression checks passed.');
