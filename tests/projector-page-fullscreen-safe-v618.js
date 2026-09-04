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
assert(feed.includes('PAGE <?=$competitorRolePage?> OF <?=$competitorRoleTotalPages?>'), 'competitor page status is not rendered directly');
assert.doesNotMatch(feed, /str_replace\(\$roleHeaderSearch,\$roleHeaderReplace,\$html\)/);
assert(safe.includes('padding-left: 5cqw') && safe.includes('padding-right: 5cqw'), 'Jack & Jill horizontal safe area missing');
assert(dcProjector.includes('padding:10vh 5vw'), 'Dance Cup four-edge safe area missing');
assert(jjControl.includes('projection-control-fullscreen-v618.js?v=619'), 'Jack & Jill control fullscreen integration missing');
assert(dcControl.includes('projection-control-fullscreen-v618.js?v=619'), 'Dance Cup control fullscreen integration missing');
assert(!jjControl.includes('position-fixed bottom-0 end-0'), 'Jack & Jill fullscreen control must not float over the page');
assert(!dcControl.includes('position-fixed bottom-0 end-0'), 'Dance Cup fullscreen control must not float over the page');
assert(fullscreen.includes('document.documentElement.requestFullscreen()'), 'fullscreen action missing');
assert.match(feed, /projector-safe-v616\.css\?v=(?:619|62\d)/, 'fresh safe-area cache key missing');
assert(!feed.includes('class="projection-fullscreen"'), 'audience Jack & Jill display must not show a fullscreen button');
assert(!dcProjector.includes('id="projectionFullscreen"'), 'audience Dance Cup display must not show a fullscreen button');
const release = Number(version.version.match(/dev(\d+)$/)?.[1] || 0);
assert(release >= 619);
assert(version.build >= 3325);
console.log('projector page status, control-only fullscreen and four-edge safe area v619+: PASS');
