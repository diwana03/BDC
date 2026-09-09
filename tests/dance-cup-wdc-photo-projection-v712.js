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
assert(projector.includes('.contestant-grid.rows-2 .contestant-photo-frame{width:clamp(66px,min(5.76vw,8.4vh),94px);height:clamp(82px,min(7.2vw,10.5vh),118px)'), 'Two-row contestant portraits must retain a 4:5 preview inside the vertical safe area');
assert(projector.includes('.rank-photo{width:clamp(46px,4.4vw,83px);height:clamp(58px,5.5vw,104px)'), 'scoreboard portraits must retain a readable 4:5 preview');
assert(projector.includes('.podium-photo{width:clamp(70px,6.4vw,125px);height:clamp(88px,8vw,156px)'), 'podium portraits must retain a readable 4:5 preview');
assert(Number(version.version.match(/^2\.3\.6-dev(\d+)$/)?.[1]||0)>=712);
assert(version.build>=3418);

console.log('Dance Cup adjusted WDC photo projection checks passed.');
