const assert = require('node:assert/strict');
const fs = require('node:fs');
const read = file => fs.readFileSync(file, 'utf8');

const feed = read('live-display/feed.php');
const state = read('live-display/state.php');
const advance = read('live-display/advance.php');
const display = read('live-display/index.php');
const safe = read('public/css/projector-safe-v616.css');
const version = JSON.parse(read('VERSION.json'));

assert(feed.includes('in_array($type,["heats_scores","score_matrix"],true)&&(string)$r["round_type"]!=="final"'));
assert(feed.includes('$matrixRows=$heatsScoreRoleItems[$role]'), 'matrix renderer is not using paged role rows');
assert(feed.includes('$heatsScoreRoleTotals[$role]?> CONTESTANTS'), 'matrix total is missing');
assert(feed.includes('PAGE <?=$heatsScorePage?> OF <?=$heatsScoreTotalPages?>'), 'matrix page number is missing');
assert(state.includes('$s["screen_type"]==="score_matrix"&&$roundType!=="final"'), 'state does not limit matrix pagination to non-final rounds');
assert(advance.includes("$pagedTypes=['competitors','callbacks','finalists','heats_scores','score_matrix']"));
assert(advance.includes("$screenType==='score_matrix'&&$roundType==='final'"), 'final matrix pagination guard missing');
assert(display.includes("'heats_scores','score_matrix'"), 'matrix auto-page timer missing');
assert.match(safe, /width: max\(\d+px, min\(\d+cqw, \d+cqh\)\)/, 'larger inline logo sizing missing');
assert(safe.includes('justify-content: center'), 'logo and title are not centered together');
assert.match(feed, /projector-safe-v616\.css\?v=62\d/, 'new header CSS cache key missing');
assert.match(version.version, /^2\.3\.3-dev62\d$/);
assert(version.build >= 3327);

console.log('projector Score Matrix pagination and closer larger logo v621: PASS');
