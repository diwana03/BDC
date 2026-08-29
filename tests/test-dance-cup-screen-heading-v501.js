const fs = require('fs');
const assert = require('assert');

const display = fs.readFileSync('admin/dance-cup/projector.php', 'utf8');
const launch = fs.readFileSync('admin/dance-cup/projector-launch.php', 'utf8');

assert(display.includes('class="screen-heading"'), 'content screens must render the shared category heading');
assert(display.includes("latest?.state?.category_name||'Dance Cup Category'"), 'heading must use the complete active category name');
assert(display.includes("round=label(latest?.state?.round_name||'')"), 'heading must include the active round');
assert(display.includes("const isHolding=type==='Holding Screen'"), 'Holding Screen must remain uncluttered');
for (const screen of ['Contestant Call','Judges','All Contestants','Scoring Progress','Live Scoreboard','Winner Podium']) {
  assert(display.includes("'" + screen + "'"), screen + ' route must remain available');
}
assert(display.includes('.screen-heading .rule{'), 'shared heading must include the gold divider');
assert(launch.includes("'&presentation=501'"), 'projector launch must invalidate the previous presentation document');

console.log('Dance Cup shared screen heading v501 passed.');
