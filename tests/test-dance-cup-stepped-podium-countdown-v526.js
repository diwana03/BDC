const assert = require('assert');
const fs = require('fs');

const projector = fs.readFileSync('admin/dance-cup/projector.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

for (const marker of [
  'align-items:end',
  '.podium-card.first{--podium-height:clamp(500px,69vh,690px)',
  '.podium-card.second{--podium-height:clamp(430px,59vh,590px)',
  '.podium-card.third{--podium-height:clamp(390px,51vh,510px)',
  'function countdownToPodium(data)',
  'let seconds=5',
  "place==='1'?'Champion':place==='2'?'2nd Place':'3rd Place'",
  'if(revealChanged){countdownToPodium(data);return}',
]) assert(projector.includes(marker), `Missing stepped podium/countdown marker: ${marker}`);

assert(projector.indexOf("classes=['second','first','third']") !== -1, 'Podium order must remain second, champion, third');
assert.strictEqual(version.version, '2.3.3-dev536');
assert.strictEqual(version.build, 3242);

console.log('OK: Dance Cup podium is stepped and every placement reveal receives a 5-4-3-2-1 countdown');
