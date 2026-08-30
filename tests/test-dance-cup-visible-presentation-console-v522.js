const assert = require('assert');
const fs = require('fs');

const control = fs.readFileSync('admin/dance-cup/projection-control.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

assert(control.includes('col-lg-4 presentation-sidebar'), 'presentation controls must remain beside the main console at laptop widths');
assert(control.includes('id="presentationEffects"'), 'effects must have a directly visible presentation panel');
assert(control.includes('id="premiumBackground"'), 'premium backgrounds must have a directly visible panel');
assert(control.includes('.presentation-effects{order:-3'), 'effects must appear first in the presentation sidebar');
assert(control.includes('.premium-background{order:-2'), 'premium backgrounds must appear before secondary paging controls');
for (const target of ['#sendScreenLive', '#officialResultReveal', '#presentationEffects', '#premiumBackground']) {
  assert(control.includes(`href="${target}"`), `quick navigation missing ${target}`);
}
for (const effect of ['hearts', 'balloons', 'heart_smiles', 'finger_hearts', 'gold_rain', 'champion_impact']) {
  assert(control.includes(`'${effect}'`), `presentation effect missing ${effect}`);
}
assert.strictEqual(version.version, '2.3.3-dev523');
assert.strictEqual(version.build, 3229);

console.log('dev522 visible Dance Cup presentation console checks passed');
