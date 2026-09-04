const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
const remote = read('projection-remote/index.php');
const mobile = read('app/Services/MobileProjectionRemoteService.php');
const session = read('app/Services/LiveDisplaySessionService.php');
const control = read('admin/live-screen/control.php');
const action = read('admin/live-screen/live-action.php');
const feed = read('live-display/feed.php');
const state = read('live-display/state.php');
const advance = read('live-display/advance.php');
const display = read('live-display/index.php');

assert.match(remote, /ScoringFlightService::summary\(\$pdo,\(int\)\$round\['id'\],\$test\)/, 'mobile remote must load saved flight rounds');
assert.match(remote, /data-screen="flights" data-page="<\?=\$flightNumber\?>"/, 'mobile flight buttons must send their exact round number');
assert.match(remote, /body\.set\('page_number',button\.dataset\.page\)/, 'mobile command must transmit the selected flight/page');
assert.match(mobile, /'flights'=>'Flight Round'/, 'server allowlist must permit flight commands');
assert.match(mobile, /That Flight Round is not configured/, 'server must reject nonexistent flight rounds');
assert.match(feed, /\$flight = max\(1, \$page\)/, 'projector must render the flight explicitly selected by the remote');

assert.match(remote, /data-effect="countdown"/, 'mobile remote must expose a standalone countdown');
assert.match(control, /data-effect="countdown"/, 'desktop control must expose a standalone countdown');
assert.match(mobile, /'effect_type'=>\$callbackReveal\?'countdown':'\\?'/, 'mobile callback reveal must attach the countdown to the same update');
assert.match(action, /\$callbackReveal = .*screen_type.*callbacks/, 'desktop callback command must select the countdown path');
assert.match(action, /\$_POST\['effect_type'\] = 'countdown'/, 'desktop callback reveal must attach the countdown to the same update');
assert.match(session, /effect_version=effect_version\+:fx_bump/, 'callback screen and countdown must update atomically');

for (const source of [mobile, session, control, feed, state, advance, display]) {
  assert.match(source, /judge_call/, 'one-by-one judge calling must be wired through every runtime layer');
}
assert.match(feed, /CALLING JUDGE \{\$judgePage\} OF /, 'judge-call projector must show current and total judge number');
assert.match(session, /SELECT COUNT\(\*\) FROM \{\$judgeTable\} WHERE round_id=:r/, 'judge-call page must be bounded by assigned judges');
assert.match(advance, /\$screenType === 'judge_call'/, 'auto page must advance one judge at a time');

console.log('OK: mobile flight rounds, callback/standalone countdown, and one-by-one judge calling are wired across Test/Live projector paths.');
