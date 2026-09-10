const fs = require('fs');
const assert = require('assert');

const control = fs.readFileSync('admin/dance-cup/projection-control.php', 'utf8');

const allowedScreens = control.match(/\$allowed=\[([^\]]+)\]/)?.[1] || '';
assert(allowedScreens.includes("'contestant'"), 'theme updates must accept the singular live contestant screen state');
assert(
  control.includes("SET theme=:theme,state_version=state_version+1") && !control.match(/action==='theme'[\s\S]{0,500}screen_type=:screen/),
  'theme updates must preserve the active projector screen'
);
assert(
  control.includes("$themes=['midnight_wine','obsidian_gold','ivory_wine','pearl_navy']"),
  'all four premium projection themes must remain available'
);

console.log('Dance Cup live-contestant theme selection v499 passed.');
