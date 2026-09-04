const fs = require('fs');
const assert = require('assert');
const read = file => fs.readFileSync(file, 'utf8');

const service = read('app/Services/MobileProjectionRemoteService.php');
const mobile = read('projection-remote/index.php');
const control = read('admin/live-screen/control.php');
const workspace = read('admin/live-screen/projection-workspace.php');
const feed = read('live-display/feed.php');
const roster = read('public/css/projector-roster-v615.css');

assert.match(service, /public static function assignedJudges/);
assert.match(service, /bdc_test_scoring_judges/);
assert.match(service, /ORDER BY is_chief DESC,judge_order,id/);
assert.match(service, /'judges'=>\$round\?self::assignedJudges/);

assert.match(mobile, /id="judgeCallPanel"/);
assert.match(mobile, /id="judgeSelect"/);
assert.match(mobile, /Call Selected Judge/);
assert.match(mobile, /action==='call_judge'/);
assert.match(mobile, /body\.set\('screen_type','judge_call'\)/);
assert.match(mobile, /body\.set\('page_number',judgeSelect\.value\)/);
assert.match(mobile, /unset\(\$screens\['flights'\],\$screens\['judge_call'\]\)/);
assert.match(mobile, /No assigned judges/);

assert.match(control, /MobileProjectionRemoteService::assignedJudges/);
assert.match(control, /id="judgeCallSelect"/);
assert.match(control, /id="callSelectedJudge"/);
assert.match(control, /data-screen="judge_call"/);
assert.match(control, /form-select-lg/);
assert.match(control, /select\.form-select option\{background-color:#fff!important;color:#111827!important/);
assert.match(workspace, /select\.form-select option\{background-color:#fff!important;color:#111827!important/);
assert.match(mobile, /\.judge-call select option\{background-color:#07101f!important;color:#fff!important/);

assert.match(feed, /projector-roster-v615\.css\?v=(?:627|628)/);
assert.match(roster, /\.stage \.list > \.judge-card:only-child/);
assert.match(roster, /width: min\(88%, 1180px\)/);
assert.match(roster, /height: min\(88%, 590px\)/);
assert.match(roster, /width: clamp\(220px/);
assert.match(roster, /\.stage \.judge-country \{[\s\S]*?flex-direction: column/);
assert.match(roster, /\.stage \.judge-country-name \{[\s\S]*?overflow-wrap: anywhere/);

console.log('Projector judge selector and large single-judge display regression checks passed.');
