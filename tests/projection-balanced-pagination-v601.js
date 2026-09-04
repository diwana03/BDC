const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

function balancedSizes(count, pages) {
  const base = Math.floor(count / pages);
  const remainder = count % pages;
  return Array.from({ length: pages }, (_, index) => base + (index < remainder ? 1 : 0));
}

assert.deepEqual(balancedSizes(38, 2), [19, 19]);
assert.deepEqual(balancedSizes(34, 2), [17, 17]);
assert.deepEqual(balancedSizes(64, 3), [22, 21, 21]);
assert.deepEqual(balancedSizes(1, 3), [1, 0, 0]);
assert.deepEqual(balancedSizes(0, 2), [0, 0]);

const root = path.resolve(__dirname, '..');
const service = fs.readFileSync(path.join(root, 'app/Services/ProjectionLayoutService.php'), 'utf8');
const feed = fs.readFileSync(path.join(root, 'live-display/feed.php'), 'utf8');

assert.match(service, /function balancedPageSlice\(array \$items,int \$page,int \$pages\):array/);
assert.match(service, /\$offset=\(\$page-1\)\*\$base\+min\(\$page-1,\$remainder\)/);
assert.match(feed, /ProjectionLayoutService::balancedPageSlice\(\$roleItems,\$page,\$competitorRoleTotalPages\)/);
assert.doesNotMatch(feed, /array_slice\(\$roleItems,\(\$page-1\)\*\$competitorRoleCapacity/);

console.log('Balanced projector pagination regression checks passed.');
