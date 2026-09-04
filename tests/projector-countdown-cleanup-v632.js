const fs = require('fs');
const assert = require('assert');

const display = fs.readFileSync('live-display/index.php', 'utf8');

assert.match(
  display,
  /function countdown\(\)\{[\s\S]*?fxTimer=setTimeout\(stopEffect,5100\)\}/,
  'Countdown must guarantee that the effects overlay is cleared after five seconds.'
);
assert.match(
  display,
  /if\(s\.effect_type==='countdown'\)countdown\(\)/,
  'Countdown effect dispatch must remain connected.'
);

console.log('Projector countdown cleanup v632: PASS');
