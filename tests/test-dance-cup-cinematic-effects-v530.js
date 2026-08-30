const assert = require('assert');
const fs = require('fs');

const projector = fs.readFileSync('admin/dance-cup/projector.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

for (const marker of [
  '.fx-cinematic',
  'animation:cinematicWash 10s',
  'animation:fxImpact 7s',
  'clearEffectAfter(run,12000)',
  'clearEffectAfter(run,7600)',
  'clearEffectAfter(run,11200)',
  '(6.5+Math.random()*3.5)',
  'let lastHash=',
  'effectRun=0',
]) assert(projector.includes(marker), `Missing cinematic-effect marker: ${marker}`);

assert(!projector.includes("launchFireworks(place==='1'?"), 'Cinematic fireworks must remain separate from result reveals');
assert.strictEqual(version.version, '2.3.3-dev530');
assert.strictEqual(version.build, 3236);

console.log('OK: all Dance Cup effects are cinematic, sustained and operator controlled');
