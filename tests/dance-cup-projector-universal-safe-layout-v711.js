'use strict';

const fs = require('fs');
const assert = require('assert');

const projector = fs.readFileSync('admin/dance-cup/projector.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

assert(projector.includes('#app{width:100vw;height:100vh;padding:10vh 5vw'), 'Every Dance Cup screen must use the 10%/5% safe canvas');
assert(projector.includes('<div class="event-brand"><div class="logo">'), 'Logo and event title must share one close heading group');
assert(projector.includes('.event-brand{min-width:0;display:flex;align-items:center;justify-content:center;gap:'), 'Logo/title group must remain close and centered');
assert(projector.includes('transform:translateY(clamp(3px,.55vh,7px))'), 'Logo must sit lower beside the event title');
assert(projector.includes('.screen-content>*{max-width:100%;max-height:100%}'), 'Every screen payload must remain inside the safe canvas');
assert(projector.includes('.board{width:min(1720px,100%)'), 'Scoreboard must respect horizontal safe margins');
assert(projector.includes('.call{width:min(1680px,100%)'), 'Contestant Call must respect horizontal safe margins');
assert(projector.includes('.call-layout{width:100%;height:min(100%,760px)'), 'Contestant Call must respect vertical safe margins');
assert(projector.includes('.podium{width:min(1500px,100%)'), 'Podium must respect horizontal safe margins');
assert(projector.includes('.podium-card{height:var(--podium-height);max-height:100%'), 'Podium cards must respect vertical safe margins');
for (const screen of ['Holding Screen','Contestant Call','All Contestants','Judges','Scoring Progress','Live Scoreboard','Winner Podium']) {
  assert(projector.includes(screen), `Shared safe shell must retain ${screen}`);
}
assert.strictEqual(version.version, '2.3.6-dev712');
assert.strictEqual(version.build, 3418);

console.log('Dance Cup universal projector safe-layout checks passed.');
