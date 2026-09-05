const fs = require('fs');
const assert = require('assert');

const jackJill = fs.readFileSync('live-display/index.php', 'utf8');
const danceCup = fs.readFileSync('admin/dance-cup/projector.php', 'utf8');
const feed = fs.readFileSync('live-display/feed.php', 'utf8');

const jackJillControl = fs.readFileSync('admin/live-screen/control.php', 'utf8');
const danceCupControl = fs.readFileSync('admin/dance-cup/projection-control.php', 'utf8');
const fullscreenHelper = fs.readFileSync('public/js/projection-control-fullscreen-v618.js', 'utf8');

for (const [name, source] of [['Jack & Jill', jackJill], ['Dance Cup', danceCup]]) {
  assert(!source.includes('projectorFullscreen'), `${name} audience projector must not expose a fullscreen button`);
  assert(!source.includes('PRESS F11 FOR FULL SCREEN'), `${name} audience projector must not show desktop instructions on mobile`);
}
for (const [name, source] of [['Jack & Jill', jackJillControl], ['Dance Cup', danceCupControl]]) {
  assert(source.includes('data-fullscreen-control'), `${name} control panel must retain its fullscreen action`);
}
assert(fullscreenHelper.includes('requestFullscreen'), 'Control-panel fullscreen helper is missing');

assert(feed.includes('$competitorRoleCapacity=$competitorRolePaged?15:'), 'Competitor slides must remain capped at 15 per role');
assert(feed.includes('$competitorRoleTotals[$role]=count($roleItems)'), 'Headers must retain true Leader and Follower totals');

console.log('Projector fullscreen and 15-per-role pagination regression checks passed.');
