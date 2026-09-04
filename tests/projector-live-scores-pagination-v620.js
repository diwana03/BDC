const assert = require('node:assert/strict');
const fs = require('node:fs');

const read = file => fs.readFileSync(file, 'utf8');
const feed = read('live-display/feed.php');
const state = read('live-display/state.php');
const advance = read('live-display/advance.php');
const display = read('live-display/index.php');
const safe = read('public/css/projector-safe-v616.css');
const version = JSON.parse(read('VERSION.json'));

assert(feed.includes('projection-heading-row'), 'logo and event heading row missing');
assert(feed.includes('projector-safe-v616.css?v=620'), 'projector safe CSS cache key not refreshed');
assert(safe.includes('.projection-heading-row > .projection-brand'), 'inline logo layout missing');
assert(!feed.includes('<div class="stage"><div class="projection-brand">'), 'logo still positioned as a detached stage overlay');

assert.match(feed, /\$heatsScoreTotalPages=max\(1,\(int\)ceil\(max\(\$heatsScoreRoleTotals\)\/12\)\)/);
assert.match(feed, /ProjectionLayoutService::balancedPageSlice\(\$scoreRows,\$heatsScorePage,\$heatsScoreTotalPages\)/);
assert(feed.includes('PAGE <?=$heatsScorePage?> OF <?=$heatsScoreTotalPages?>'), 'Live Contestant Scores page label missing');
assert(feed.includes('$scoreRows=$heatsScoreRoleItems[$role]'), 'Live Contestant Scores renderer is not using the paged role rows');
assert(state.includes('$roleCapacity=$s["screen_type"]==="heats_scores"?12:15'), 'state page count does not use 12 rows for Live Contestant Scores');
assert(advance.includes("$pagedTypes=['competitors','callbacks','finalists','heats_scores']"), 'advance endpoint does not support Live Contestant Scores');
assert(display.includes("['competitors','callbacks','finalists','heats_scores'].includes(s.screen_type)"), 'audience auto-page timer does not support Live Contestant Scores');
assert.equal(version.version, '2.3.3-dev620');
assert.equal(version.build, 3326);

console.log('projector inline logo and Live Contestant Scores pagination v620: PASS');
