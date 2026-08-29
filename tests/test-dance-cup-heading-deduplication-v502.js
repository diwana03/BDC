const fs = require('fs');
const assert = require('assert');

const display = fs.readFileSync('admin/dance-cup/projector.php', 'utf8');

assert(display.includes("categoryName.textContent=(data.state.category_name||'Category')+' · '+label(data.state.round_name||'')"), 'top masthead must show one category and round line');
assert(!display.includes('screen-heading'), 'content must not duplicate the category heading');
assert(display.includes('.event p{font-size:clamp(18px,1.7vw,32px);font-weight:800;color:var(--muted);margin:.85em 0 0'), 'original category line must be larger and sit lower');
assert(display.includes('.view{width:100%;height:100%;min-height:0;display:grid;place-items:center'), 'all projection views, including Holding Screen, must remain centred');
assert(!display.includes("esc(data.state.holding_message||'Next contestant preparing')"), 'Holding Screen must not show the next-contestant message');

console.log('Dance Cup category heading deduplication v502 passed.');
