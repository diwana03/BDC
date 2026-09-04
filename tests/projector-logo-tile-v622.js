const assert = require('node:assert/strict');
const fs = require('node:fs');
const read = file => fs.readFileSync(file, 'utf8');

const feed = read('live-display/feed.php');
const safe = read('public/css/projector-safe-v616.css');
const version = JSON.parse(read('VERSION.json'));

assert(feed.includes('<div class="projection-heading-row"><div class="projection-brand">'), 'projector heading row is missing');
assert(feed.includes('<div class="projection-heading-copy"><div class="event">'), 'event, division and round heading are not grouped beside the logo');
assert(feed.includes(') ?></div></div></div><?php if ($splitRoleScreen)'), 'projector heading group does not close before screen content');
assert(feed.includes('projector-safe-v616.css?v=622'), 'projector logo CSS cache key is stale');
assert.doesNotMatch(feed, /ob_start\(static fn\(string \$html\)/, 'projector must not depend on a full-page output rewrite');
assert(safe.includes('grid-template-columns: auto minmax(0, auto)'), 'logo and heading copy are not arranged as one centered group');
assert(safe.includes('width: max(96px, min(10cqw, 17cqh))'), 'approved larger logo tile size is missing');
assert(safe.includes('background: #fff !important'), 'solid white logo tile background is missing');
assert(safe.includes('margin-top: max(6px'), 'approved breathing room above the logo is missing');
assert(safe.includes('align-self: end'), 'logo is not aligned down toward the round title');
const release = Number(version.version.match(/dev(\d+)$/)?.[1] || 0);
assert(release >= 622);
assert(version.build >= 3328);

console.log('projector white logo tile and full-heading placement v622: PASS');
