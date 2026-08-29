const fs = require('fs');
const assert = require('assert');

const display = fs.readFileSync('admin/dance-cup/projector.php', 'utf8');

assert(display.includes("categoryName.textContent=''"), 'top masthead must not repeat category and round');
assert(display.includes('.event p:empty{display:none}'), 'empty duplicate masthead line must not reserve space');
assert(display.includes("'<header class=\"screen-heading\"><h2>'"), 'content must retain one prominent category title');
assert(!display.includes("<p>'+esc(round)+(round?' · ':'") , 'content heading must not repeat round and screen labels');
assert(!display.includes('.screen-heading p{'), 'removed heading subtitle must not reserve vertical space');
assert(display.includes('.view.is-holding{grid-template-rows:1fr;place-items:center}'), 'Holding Screen must be truly centred after the shared heading layout');
assert(!display.includes("esc(data.state.holding_message||'Next contestant preparing')"), 'Holding Screen must not show the next-contestant message');

console.log('Dance Cup category heading deduplication v502 passed.');
