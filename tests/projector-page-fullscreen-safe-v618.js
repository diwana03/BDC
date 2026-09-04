const assert = require('node:assert/strict');
const fs = require('node:fs');
const read = file => fs.readFileSync(file, 'utf8');

const feed = read('live-display/feed.php');
const safe = read('public/css/projector-safe-v616.css');
const dcProjector = read('admin/dance-cup/projector.php');
const jjControl = read('admin/live-screen/control.php');
const dcControl = read('admin/dance-cup/projection-control.php');
const fullscreen = read('public/js/projection-control-fullscreen-v618.js');
const version = JSON.parse(read('VERSION.json'));

assert.match(feed, /\$competitorRoleTotals=\["leader"=>0,"follower"=>0\]/);
assert.match(feed, /PAGE '.\$competitorRolePage.' OF '.\$competitorRoleTotalPages/);
assert.match(feed, /str_replace\(\$roleHeaderSearch,\$roleHeaderReplace,\$html\)/);
assert(safe.includes('padding-left: 5cqw') && safe.includes('padding-right: 5cqw'), 'Jack & Jill horizontal safe area missing');
assert(dcProjector.includes('padding:10vh 5vw'), 'Dance Cup four-edge safe area missing');
assert(jjControl.includes('projection-control-fullscreen-v618.js?v=618'), 'Jack & Jill control fullscreen integration missing');
assert(dcControl.includes('projection-control-fullscreen-v618.js?v=618'), 'Dance Cup control fullscreen integration missing');
assert(fullscreen.includes('document.documentElement.requestFullscreen()'), 'fullscreen action missing');
assert.strictEqual(version.version, '2.3.3-dev618');
assert.strictEqual(version.build, 3324);
console.log('projector page status, control fullscreen and four-edge safe area v618: PASS');
