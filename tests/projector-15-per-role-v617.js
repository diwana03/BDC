const assert = require('node:assert/strict');
const fs = require('node:fs');

const feed = fs.readFileSync('live-display/feed.php', 'utf8');
const state = fs.readFileSync('live-display/state.php', 'utf8');
const service = fs.readFileSync('app/Services/ProjectionLayoutService.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

const balancedSizes = (count, pages) => {
  const base = Math.floor(count / pages);
  const remainder = count % pages;
  return Array.from({length: pages}, (_, index) => base + (index < remainder ? 1 : 0));
};

assert.deepEqual(balancedSizes(26, Math.ceil(26 / 15)), [13, 13]);
assert.deepEqual(balancedSizes(27, Math.ceil(27 / 15)), [14, 13]);
assert.deepEqual(balancedSizes(46, Math.ceil(46 / 15)), [12, 12, 11, 11]);
assert.match(feed, /\$competitorRolePaged=in_array\(\$type,\["competitors","callbacks","finalists"\],true\)/);
assert.match(feed, /\$competitorRoleCols=\$competitorRolePaged\?3:/);
assert.match(feed, /\$competitorRoleCapacity=\$competitorRolePaged\?15:/);
assert.match(feed, /ProjectionLayoutService::balancedPageSlice\(\$roleItems,\$page,\$competitorRoleTotalPages\)/);
assert.match(state, /\$roleCapacity=15;/);
assert.match(service, /function balancedPageSlice\(array \$items,int \$page,int \$pages\):array/);
assert.strictEqual(version.version, '2.3.3-dev617');
assert.strictEqual(version.build, 3323);
console.log('15-per-role balanced projector pagination v617: PASS');
