const fs=require('fs'),assert=require('assert');
const projector=fs.readFileSync('admin/dance-cup/projector.php','utf8');
const feed=fs.readFileSync('admin/dance-cup/projection-feed.php','utf8');
const version=JSON.parse(fs.readFileSync('VERSION.json','utf8'));

for(const type of ['couple','duo','pro_am','team']){
  assert(projector.includes(`body[data-entry-type=${type}] .contestant-grid`),`missing wider ${type} grid`);
}
assert(projector.includes("document.body.dataset.groupEntry=['couple','duo','pro_am','team'].includes(entryType)?'1':'0'"),'active category must select the group-safe layout');
assert(feed.includes("in_array($entryType,['couple','duo','pro_am','team'],true)"),'ampersand-named teams must use the same two-name projection rule');
assert(projector.includes('pageSize=groupEntry?8:10,columnCount=groupEntry?4:5'),'group contestant pages must use four wider cards and eight entries');
assert(projector.includes("pageSize=Number(document.body.dataset.groupEntry)===1?5:6"),'scoreboards must use fewer readable rows');
assert(projector.includes('<div class="name"><strong>'),'scoreboard must reserve a readable name block');
assert(projector.includes("</strong>'+identity(row)"),'scoreboard must show flags and country labels below the name');
assert(projector.includes('body[data-group-entry="1"] .contestant-photo-frame .profile-photo'),'group contestant photos are not protected');
assert(projector.includes('object-fit:contain!important'),'group photos must show the full saved preview');
assert(projector.includes('body[data-group-entry="1"] .contestant-grid .contestant-name{white-space:normal!important;overflow:visible!important;text-overflow:clip!important'),'group names must not be ellipsized');
assert(projector.includes('body[data-group-entry="1"] .rank-row .name{white-space:normal;overflow:visible;text-overflow:clip'),'group scoreboard names must not be ellipsized');
assert(projector.includes('.podium-card.first{--podium-height:100%'),'champion card must fit the available stage height');
assert(projector.includes('.podium-card.second{--podium-height:88%'),'second place must retain the stepped layout');
assert(projector.includes('.podium-card.third{--podium-height:80%'),'third place must retain the stepped layout');
assert(!projector.includes('--podium-height:clamp(390px,51vh,510px)'),'fixed third-place height can still clip behind the footer');
assert(version.version==='2.3.6-dev736'&&version.build===3442,'release metadata mismatch');
console.log('Dance Cup group safe layout v736 checks passed');
