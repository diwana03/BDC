const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const feed = fs.readFileSync(path.resolve(__dirname, '../live-display/feed.php'), 'utf8');

assert.match(feed, /\.competitor-photo-frame\{flex:0 0 auto;width:clamp\(46px,6\.2vh,68px\);height:clamp\(46px,6\.2vh,68px\)/);
assert.match(feed, /\.competitor-card \.photo\{display:block;width:100%;height:100%;margin:0/);
assert.match(feed, /\.judge-photo-frame\{position:relative;align-self:center;width:clamp\(64px,min\(8vw,13vh\),170px\);height:clamp\(64px,min\(8vw,13vh\),170px\)/);
assert.match(feed, /\.judge-photo,\.judge-photo-fallback\{grid-area:1\/1;width:100%;height:100%/);
assert.doesNotMatch(feed, /\.competitor-card \.photo\{width:min\(58cqw,58cqh\)/);
assert.doesNotMatch(feed, /\.judge-photo,\.judge-photo-fallback\{grid-area:1\/1;width:min\(64cqw,64cqh\)/);

console.log('Current projector photo sizing regression checks passed.');
