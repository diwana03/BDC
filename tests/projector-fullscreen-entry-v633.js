const fs = require('fs');
const assert = require('assert');

const jackJill = fs.readFileSync('live-display/index.php', 'utf8');
const danceCup = fs.readFileSync('admin/dance-cup/projector.php', 'utf8');
const feed = fs.readFileSync('live-display/feed.php', 'utf8');

for (const [name, source] of [['Jack & Jill', jackJill], ['Dance Cup', danceCup]]) {
  assert(source.includes('projectorFullscreen'), `${name} projector must expose its own fullscreen button`);
  assert(source.includes('requestFullscreen'), `${name} projector must request fullscreen in the projector document`);
  assert(source.includes("fullscreenchange"), `${name} projector must react to fullscreen exit`);
  assert(source.includes('ENTER FULL SCREEN'), `${name} projector must provide a clear button label`);
}

assert(feed.includes('$competitorRoleCapacity=$competitorRolePaged?15:'), 'Competitor slides must remain capped at 15 per role');
assert(feed.includes('$competitorRoleTotals[$role]=count($roleItems)'), 'Headers must retain true Leader and Follower totals');

console.log('Projector fullscreen and 15-per-role pagination regression checks passed.');
