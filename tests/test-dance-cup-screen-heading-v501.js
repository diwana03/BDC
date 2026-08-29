const fs = require('fs');
const assert = require('assert');

const display = fs.readFileSync('admin/dance-cup/projector.php', 'utf8');
const launch = fs.readFileSync('admin/dance-cup/projector-launch.php', 'utf8');

assert(display.includes("categoryName.textContent=(data.state.category_name||'Category')+' · '+label(data.state.round_name||'')"), 'masthead must retain the complete category and round');
assert(!display.includes('class="screen-heading"'), 'content must not repeat the masthead category');
for (const screen of ['Contestant Call','Judges','All Contestants','Scoring Progress','Live Scoreboard','Winner Podium']) {
  assert(display.includes("'" + screen + "'"), screen + ' route must remain available');
}
assert(launch.includes("'&presentation=503'"), 'projector launch must invalidate the previous presentation document');

console.log('Dance Cup shared screen heading v501 passed.');
