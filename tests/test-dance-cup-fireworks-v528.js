const assert = require('assert');
const fs = require('fs');

const control = fs.readFileSync('admin/dance-cup/projection-control.php', 'utf8');
const projector = fs.readFileSync('admin/dance-cup/projector.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

assert(control.includes("'fireworks'=>'🎆 Fireworks'"), 'Projection Control must expose manual fireworks');
assert(control.includes("'gold_rain','fireworks','champion_impact'"), 'Fireworks must be validated server-side');
for (const marker of ['function launchFireworks(count=26,run=effectRun)', "if(type==='fireworks'){launchFireworks(26,run);clearEffectAfter(run,12000);return}", '@keyframes fireworkSpark', 'function clearEffectAfter(run,milliseconds)']) {
  assert(projector.includes(marker), `Missing fireworks marker: ${marker}`);
}
assert(!projector.includes("launchFireworks(place==='1'?14:place==='2'?9:6)"), 'Result reveals must not trigger fireworks automatically');
assert.strictEqual(version.version, '2.3.3-dev531');
assert.strictEqual(version.build, 3237);

console.log('OK: Dance Cup has operator-controlled fireworks with no automatic result trigger');
