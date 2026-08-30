const assert = require('assert');
const fs = require('fs');

const control = fs.readFileSync('admin/dance-cup/projection-control.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

assert(control.includes('LIVE PRESENTATION CONSOLE'), 'presentation controls must be immediately visible near the top');
assert(control.includes('Background &amp; Effects'), 'background and effects must share one clear console');
assert(control.includes('class="console-grid"'), 'presentation console must use a responsive two-panel layout');
assert(control.includes('class="theme-grid"'), 'premium backgrounds must be visible inside the console');
assert(control.includes('class="effect-grid"'), 'effects must be visible inside the console');
assert(!control.includes('class="quick-nav"'), 'ineffective shortcut-only navigation must be removed');
assert(control.includes("$changed==='theme'"), 'background changes must show confirmation');
assert(control.includes("$changed==='effect'"), 'effect commands must show confirmation');
for (const effect of ['hearts', 'balloons', 'heart_smiles', 'finger_hearts', 'gold_rain', 'fireworks', 'champion_impact']) {
  assert(control.includes(`'${effect}'`), `presentation effect missing ${effect}`);
}
assert.strictEqual(version.version, '2.3.3-dev530');
assert.strictEqual(version.build, 3236);

console.log('dev522 visible Dance Cup presentation console checks passed');
