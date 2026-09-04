const fs = require('fs');
const assert = require('assert');
const read = file => fs.readFileSync(file, 'utf8');

const live = read('admin/scoring/judge-control.php');
const test = read('admin/scoring-tests/automatic-inline.php');
const css = read('public/css/judge-control-premium-v627.css');

assert.match(live, /judge-control-premium-v627\.css\?v=627/);
assert.match(test, /judge-control-premium-v627\.css\?v=627/);
assert.match(live, /if\(\$status==='submitted'\).*?Reopen Scoring/s);
assert.match(live, /AutomaticJudgeBrowserService::unlock/);
assert.match(live, /action.*value="unlock"/s);
assert.match(test, /badge\.text-bg-success.*?Reopen Scoring/s);
assert.match(test, /TestAutomaticJudgeService::unlock/);
assert.match(css, /\.wrap > \.head/);
assert.match(css, /\.wrap \.judge:has\(\.status\.submitted\)/);
assert.match(css, /\.wrap \.unlock \.btn\.danger/);
assert.match(css, /#automaticJudgeLivePanel > \.card-header/);

console.log('Premium Test/Live judge control and submitted-judge reopen checks passed.');
