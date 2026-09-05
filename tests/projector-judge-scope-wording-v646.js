const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
const feed = read('live-display/feed.php');
const mobile = read('app/Services/MobileProjectionRemoteService.php');

for (const source of [feed, mobile]) {
  assert.match(source, /JUDGING LEADERS/);
  assert.match(source, /JUDGING FOLLOWERS/);
  assert.match(source, /JUDGING LEADERS & FOLLOWERS/);
  assert.doesNotMatch(source, /["']Leaders Only["']/);
  assert.doesNotMatch(source, /["']Followers Only["']/);
}

assert.match(feed, /class="judge-scope"><\?=e\(\$scopeLabel\)\?>/,
  'audience judge cards must render the clarified assignment label');
assert.match(mobile, /'scope_label'=>\$scope===/, 
  'mobile projector state must expose the same assignment wording');

console.log('OK: judge assignment wording is clear and consistent across projector and mobile remote state.');
