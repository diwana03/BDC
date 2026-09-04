const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const feed = fs.readFileSync(path.resolve(__dirname, '../live-display/feed.php'), 'utf8');

assert.match(feed, /\.competitor-card \.photo\{width:clamp\(44px,min\(5vw,8vh\),96px\);height:clamp\(44px,min\(5vw,8vh\),96px\)/);
assert.match(feed, /\.competitor-photo-frame\{align-self:stretch;min-width:0;min-height:46px/);
assert.match(feed, /\.judge-photo,.judge-photo-fallback\{grid-area:1\/1;width:clamp\(64px,min\(8vw,13vh\),170px\);height:clamp\(64px,min\(8vw,13vh\),170px\)/);
assert.match(feed, /\.judge-photo-frame\{position:relative;align-self:stretch;min-width:0;min-height:70px/);
assert.doesNotMatch(feed, /\.competitor-card \.photo\{width:min\(58cqw,58cqh\)/);
assert.doesNotMatch(feed, /\.judge-photo,.judge-photo-fallback\{grid-area:1\/1;width:min\(64cqw,64cqh\)/);

console.log('Projector photo sizing regression checks passed.');
