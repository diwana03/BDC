'use strict';

const fs = require('fs');
const assert = require('assert');

const feed = fs.readFileSync('admin/dance-cup/projection-feed.php', 'utf8');
const projector = fs.readFileSync('admin/dance-cup/projector.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

assert(feed.includes('LEFT JOIN bdc_wdc_identities w ON w.id=e.wdc_identity_id'), 'projection feed must resolve the linked WDC identity');
assert(feed.includes("wi.solo_competitor_id=e.competitor_id AND wi.status='active'"), 'older roster rows must recover the saved WDC crop through the shared competitor link');
assert(feed.includes('{$wdcPhoto} photo_url'), 'saved WDC crop priority must be reused across projection queries');
assert(feed.includes("COALESCE({$wdcPhoto},'')"), 'photo changes must invalidate the projector revision');
assert(feed.includes("if(!empty($identity['photo_url']))$result['photo_url']=$identity['photo_url']"), 'scoreboard and podium must retain the roster WDC photo');
assert(projector.includes('.grid:has(>.contestant-card) .profile-photo{width:clamp(96px,min(8.6vw,13vh),148px)!important'), 'All Contestants portraits must use the larger safe responsive size');
assert(projector.includes('.rank-photo{width:clamp(58px,5.5vw,104px)'), 'scoreboard portraits must be enlarged');
assert(projector.includes('.podium-photo{width:clamp(88px,8vw,156px)'), 'podium portraits must be enlarged');
assert.strictEqual(version.version, '2.3.6-dev713');
assert.strictEqual(version.build, 3419);

console.log('Dance Cup adjusted WDC photo projection checks passed.');
