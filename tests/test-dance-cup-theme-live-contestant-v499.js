const fs = require('fs');
const assert = require('assert');

const control = fs.readFileSync('admin/dance-cup/projection-control.php', 'utf8');

assert(
  control.includes("$allowed=['holding','contestant','judges','contestants','scoring','results','podium']"),
  'theme updates must accept the singular live contestant screen state'
);
assert(
  control.includes('name="screen_type" value="<?=e($state[\'screen_type\'])?>"'),
  'theme cards must preserve the active projector screen while changing its theme'
);
assert(
  control.includes("$themes=['midnight_wine','obsidian_gold','ivory_wine','pearl_navy']"),
  'all four premium projection themes must remain available'
);

console.log('Dance Cup live-contestant theme selection v499 passed.');
