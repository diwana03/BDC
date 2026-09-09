const assert=require('assert');
const fs=require('fs');
const read=file=>fs.readFileSync(file,'utf8');

const jjFeed=read('live-display/feed.php');
const danceProjector=read('admin/dance-cup/projector.php');
const competitorAdjust=read('admin/competitors/photo-adjust.php');
const judgeAdjust=read('admin/judges/photo-adjust.php');
const danceAdjust=read('admin/dance-cup/photo-adjust.php');
const integration=read('app/Services/ProfileIntegrationService.php');
const migration=read('database/migrations/20260909_0100_wdc_original_photo.php');

assert(jjFeed.includes('aspect-ratio:4/5!important'),'J&J competitor projector frames must use the saved 4:5 preview');
assert(jjFeed.includes('.stage .matching-list img.matching-photo'),'J&J matching cards must use the same portrait preview');
assert(jjFeed.includes('.stage .podium-photo{width:min(4.8vw,7.2vh)!important;height:min(6vw,9vh)!important'),'J&J podium photos must remain 4:5 portraits');
assert(jjFeed.includes('width:clamp(94px,min(11.04vw,16.8vh),168px)!important;height:clamp(118px,min(13.8vw,21vh),210px)!important'),'J&J judge projector photos must remain 4:5 portraits');

assert(danceProjector.includes('transform:none'),'Dance Cup projector must not add a hidden zoom to saved previews');
assert(danceProjector.includes('width:clamp(99px,min(7.2vw,14.4vh),141px);height:clamp(124px,min(9vw,18vh),176px)'),'Dance Cup contestant cards must use 4:5 preview frames');
assert(danceProjector.includes('width:clamp(61px,min(8.8vw,9.6vh),106px)!important;height:clamp(76px,min(11vw,12vh),132px)!important'),'Dance Cup judge cards must use 4:5 preview frames');

for(const [label,source] of [['competitor',competitorAdjust],['judge',judgeAdjust],['Dance Cup',danceAdjust]]){
  assert(source.includes('value="restore_original"'),`${label} editor must offer Restore Original Photo`);
  assert(source.includes('id="resetView"'),`${label} editor must offer Reset View`);
  assert(source.includes("zoom.value='1';x=0;y=0;draw()"),`${label} Reset View must clear zoom and position`);
}
assert(migration.includes('ADD COLUMN original_photo_url'),'Dance Cup identities must preserve an original photo');
assert(migration.includes('SET original_photo_url=photo_url'),'existing Dance Cup photos must become a safe restore baseline');
assert(danceAdjust.includes('original_photo_url=COALESCE(original_photo_url,:source)'),'Dance Cup crop must preserve its original source');
assert(integration.includes('photo_url=:photo,original_photo_url=:original'),'approved Dance Cup replacements must refresh the preserved original');

console.log('dev726 projector preview parity and original photo restore checks passed');
