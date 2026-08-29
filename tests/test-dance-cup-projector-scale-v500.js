const fs = require('fs');
const assert = require('assert');

const display = fs.readFileSync('admin/dance-cup/projector.php', 'utf8');
const feed = fs.readFileSync('admin/dance-cup/projection-feed.php', 'utf8');
const launch = fs.readFileSync('admin/dance-cup/projector-launch.php', 'utf8');

assert(feed.includes('$entryIdentity[(int)$entry[\'id\']]'), 'feed must build identity data from the linked contestant roster');
assert(feed.includes("if(empty($result['photo_url']))$result['photo_url']=$identity['photo_url']"), 'scoreboard fallback rows must recover the contestant photo');
assert(feed.includes("if(empty($result['country']))$result['country']=$identity['country']"), 'scoreboard fallback rows must recover the contestant country');
assert(display.includes('.screen-category{text-align:center;font-size:clamp(18px,1.7vw,32px)'), 'category heading must be larger and stronger');
assert(display.includes('width:min(1720px,98vw)'), 'live scoreboard must use more projector width');
assert(display.includes('.rank-photo{width:clamp(52px,5vw,90px)'), 'scoreboard contestant photos must be larger');
assert(display.includes('.call-layout{width:100%;height:min(72vh,760px)'), 'three-section contestant presentation must remain intact');
assert(launch.includes("'&presentation=504'"), 'projector launch must invalidate the previous presentation document');

console.log('Dance Cup projector identity, scale and position v500 passed.');
