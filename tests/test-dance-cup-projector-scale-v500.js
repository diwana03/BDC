const fs = require('fs');
const assert = require('assert');

const display = fs.readFileSync('admin/dance-cup/projector.php', 'utf8');
const feed = fs.readFileSync('admin/dance-cup/projection-feed.php', 'utf8');
const launch = fs.readFileSync('admin/dance-cup/projector-launch.php', 'utf8');

assert(feed.includes('$entryIdentity[(int)$entry[\'id\']]'), 'feed must build identity data from the linked contestant roster');
assert(feed.includes("if(!empty($identity['photo_url']))$result['photo_url']=$identity['photo_url']"), 'scoreboard and podium rows must consistently use the linked contestant photo');
assert(feed.includes("if(empty($result['country']))$result['country']=$identity['country']"), 'scoreboard fallback rows must recover the contestant country');
assert(display.includes('.screen-category{text-align:center;font-size:clamp(18px,1.7vw,32px)'), 'category heading must be larger and stronger');
assert(display.includes('width:min(1720px,100%)'), 'live scoreboard must use the full safe-area width without crossing it');
assert(display.includes('.rank-photo{width:clamp(58px,5.5vw,104px)'), 'scoreboard contestant photos must be larger');
assert(display.includes('.call-layout{width:100%;height:min(100%,760px)'), 'three-section contestant presentation must remain inside the safe-area height');
assert(/'&presentation=\d+'/.test(launch), 'projector launch must invalidate the previous presentation document');

console.log('Dance Cup projector identity, scale and position v500 passed.');
