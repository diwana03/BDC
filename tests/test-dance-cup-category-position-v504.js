const fs = require('fs');
const assert = require('assert');

const display = fs.readFileSync('admin/dance-cup/projector.php', 'utf8');

assert(display.includes("categoryName.textContent=''"), 'category must be removed from the top event masthead');
assert(display.includes('<div class="screen-category">'), 'category must render inside every projection view');
assert(display.includes('<div class="screen-content">'), 'category must be directly above the screen content');
assert(display.includes("stage.innerHTML='<div class=\"view\"><div class=\"screen-category\">'"), 'all show routes must share the lower category layout');
assert(!display.includes("esc(data.state.holding_message||'Next contestant preparing')"), 'Holding Screen must remain free of the next-contestant message');

console.log('Dance Cup lower category position v504 passed.');
