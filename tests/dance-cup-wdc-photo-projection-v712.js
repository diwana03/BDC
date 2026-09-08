'use strict';

const fs = require('fs');
const assert = require('assert');

const feed = fs.readFileSync('admin/dance-cup/projection-feed.php', 'utf8');
const projector = fs.readFileSync('admin/dance-cup/projector.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

assert(feed.includes('LEFT JOIN bdc_wdc_identities w ON w.id=e.wdc_identity_id'), 'projection feed must resolve the linked WDC identity');
assert(feed.includes("COALESCE(NULLIF(w.photo_url,''),c.photo_url) photo_url"), 'saved WDC crop must take priority over the shared-person photo');
assert(feed.includes("COALESCE(w.photo_url,''),COALESCE(c.photo_url,'')"), 'photo changes must invalidate the projector revision');
assert(feed.includes("if(!empty($identity['photo_url']))$result['photo_url']=$identity['photo_url']"), 'scoreboard and podium must retain the roster WDC photo');
assert(projector.includes('.grid:has(>.contestant-card) .profile-photo{width:clamp(88px,min(8vw,12vh),132px)!important'), 'All Contestants portraits must use the larger safe responsive size');
assert(projector.includes('.rank-photo{width:clamp(58px,5.5vw,104px)'), 'scoreboard portraits must be enlarged');
assert(projector.includes('.podium-photo{width:clamp(88px,8vw,156px)'), 'podium portraits must be enlarged');
assert.strictEqual(version.version, '2.3.6-dev712');
assert.strictEqual(version.build, 3418);

console.log('Dance Cup adjusted WDC photo projection checks passed.');
