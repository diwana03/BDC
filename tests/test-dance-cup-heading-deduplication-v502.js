const fs = require('fs');
const assert = require('assert');

const display = fs.readFileSync('admin/dance-cup/projector.php', 'utf8');

assert(display.includes("categoryName.textContent=''"), 'top masthead must not duplicate category and round');
assert(display.includes("const category=label(latest?.state?.category_name||'Dance Cup Category')+' · '+label(latest?.state?.round_name||'')"), 'presentation area must build one complete escaped category and round line');
assert(display.includes('padding:clamp(20px,3.2vh,48px) 1vw clamp(12px,1.8vh,26px)'), 'category must sit lower with a controlled gap before content');
assert(!display.includes('Final · Contestant Call'), 'presentation must not add redundant screen labels');
assert(!display.includes("esc(data.state.holding_message||'Next contestant preparing')"), 'Holding Screen must not show the next-contestant message');

console.log('Dance Cup category heading deduplication v502 passed.');
