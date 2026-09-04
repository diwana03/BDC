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
assert.match(feed, /projector-safe-v616\.css\?v=62\d/, 'projector safe CSS cache key not refreshed');
assert(safe.includes('.projection-heading-row > .projection-brand'), 'inline logo layout missing');
assert(!feed.includes('<div class="stage"><div class="projection-brand">'), 'logo still positioned as a detached stage overlay');

assert.match(feed, /\$heatsScoreTotalPages=max\(1,\(int\)ceil\(max\(\$heatsScoreRoleTotals\)\/12\)\)/);
assert.match(feed, /ProjectionLayoutService::balancedPageSlice\(\$scoreRows,\$heatsScorePage,\$heatsScoreTotalPages\)/);
assert(feed.includes('PAGE <?=$heatsScorePage?> OF <?=$heatsScoreTotalPages?>'), 'Live Contestant Scores page label missing');
assert(feed.includes('$scoreRows=$heatsScoreRoleItems[$role]'), 'Live Contestant Scores renderer is not using the paged role rows');
assert(state.includes('["heats_scores","score_matrix"],true)?12:15'), 'state page count does not use 12 rows for Live Contestant Scores');
assert(advance.includes("'heats_scores','score_matrix'"), 'advance endpoint does not support Live Contestant Scores');
assert(display.includes("'heats_scores','score_matrix'"), 'audience auto-page timer does not support Live Contestant Scores');
const release = Number(version.version.match(/dev(\d+)$/)?.[1] || 0);
assert(release >= 620);
assert(version.build >= 3326);

console.log('projector inline logo and Live Contestant Scores pagination v620: PASS');
