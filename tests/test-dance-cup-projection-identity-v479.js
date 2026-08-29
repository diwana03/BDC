const fs=require('fs'),assert=require('assert'),read=f=>fs.readFileSync(f,'utf8');
const feed=read('admin/dance-cup/projection-feed.php');
const display=read('admin/dance-cup/projector.php');
for(const marker of ['LEFT JOIN bdc_competitors','LEFT JOIN bdc_judges','c.country,c.photo_url','d.country,d.country_code,d.photo_url','CountryFlagService::emoji'])assert(feed.includes(marker),'projection feed missing identity field '+marker);
for(const marker of ['profile-photo','rank-photo','podium-photo','item?.photo_url','item?.flag','item?.country','data.state.event_name'])assert(display.includes(marker),'projector missing identity rendering '+marker);
assert(display.includes("<h2>'+esc(data.state.event_name||'BDC Dance Cup')+'</h2>"),'holding screen must feature event name');
for(const marker of ['function contestant','function contestants','function judges','function results','function podium'])assert(display.includes(marker),'projection surface missing '+marker);
console.log('Dance Cup projection identity and holding event name v479 passed.');
