const fs = require('fs');
const assert = require('assert');

const display = fs.readFileSync('admin/dance-cup/projector.php', 'utf8');
const launch = fs.readFileSync('admin/dance-cup/projector-launch.php', 'utf8');

assert(display.includes("categoryName.textContent=''"), 'top masthead must release the category line');
assert(display.includes('class="screen-category"'), 'every content view must render the category lower in the presentation area');
for (const screen of ['Contestant Call','Judges','All Contestants','Scoring Progress','Live Scoreboard','Winner Podium']) {
  assert(display.includes("'" + screen + "'"), screen + ' route must remain available');
}
assert(launch.includes("'&presentation=504'"), 'projector launch must invalidate the previous presentation document');

console.log('Dance Cup shared screen heading v501 passed.');
