const assert = require('node:assert/strict');
const fs = require('node:fs');

const read = file => fs.readFileSync(file, 'utf8');
const control = read('admin/live-screen/control.php');
const workspace = read('admin/live-screen/projection-workspace.php');
const remote = read('projection-remote/index.php');
const mobile = read('app/Services/MobileProjectionRemoteService.php');
const session = read('app/Services/LiveDisplaySessionService.php');
const action = read('admin/live-screen/live-action.php');
const feed = read('live-display/feed.php');
const roster = read('public/css/projector-roster-v615.css');
const fullscreen = read('public/js/projection-control-fullscreen-v618.js');

assert.match(control, /Call Judges One by One/);
assert.match(control, /id="judgeCallSelect"/);
assert.match(control, /id="callSelectedJudge"/);
assert.match(control, /data-fullscreen-control/);
assert.match(control, /id="applyPageSettings"/);
assert.match(control, /Apply Auto Paging/);
assert.match(control, /Auto Page defaults to 15 seconds/);
assert.match(control, /\[5, 10, 15, 20, 30, 45, 60\]/);
assert.match(workspace, /allow="fullscreen"/);
assert.match(fullscreen, /targetDocument\.documentElement\.requestFullscreen\(\)/);
assert.match(fullscreen, /window\.parent\.document/);

assert.match(remote, /id="judgeCallPanel"/);
assert.match(remote, /id="judgeSelect"/);
assert.match(remote, /id="pageDelay"/);
assert.match(remote, /data-action="set_page_delay"/);
assert.match(remote, /Save Page Delay/);
assert.match(remote, /page_delay_seconds.*15/);
assert.match(mobile, /'set_page_delay'/);
assert.match(mobile, /\[5,10,15,20,30,45,60\]/);
assert.match(session, /page_delay_seconds INT UNSIGNED NOT NULL DEFAULT 15/);
assert.match(action, /page_delay_seconds.*15/);

assert.match(feed, /HEATS|round_type/);
assert.match(feed, /PAGE <\?=\$competitorRolePage\?> OF <\?=\$competitorRoleTotalPages\?>/);
assert.match(feed, /class="small flight-country"/);
assert.match(roster, /\.stage \.competitor-country \{[\s\S]*?flex-direction: column/);
assert.match(roster, /\.stage \.flight-country \{[\s\S]*?flex-direction: column/);
assert.match(roster, /\.stage \.flight-country span \{[\s\S]*?overflow-wrap: anywhere/);

console.log('Projector controls, 15-second paging, fullscreen placement and country wrapping v628: PASS');
