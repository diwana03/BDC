const fs=require('fs'),assert=require('assert');
const page=fs.readFileSync('judge-profile/index.php','utf8');
const admin=fs.readFileSync('admin/judges/index.php','utf8');
const service=fs.readFileSync('app/Services/JudgeProfileUpdateLinkService.php','utf8');
for(const marker of ['INTERVAL 6 HOUR','token_hash','hash(\'sha256\',$token)','expires_at>NOW()'])assert(service.includes(marker),marker);
for(const marker of ['generate_profile_update_link','Create 6-hour Update Link','expires in 6 hours'])assert(admin.includes(marker),marker);
for(const marker of ['profile_token','Save My Judge Profile','cropped_photo_data','qualified_divisions[]','qualified_rounds[]','biography','certification','original_photo_url'])assert(page.includes(marker),marker);
assert(!page.includes('photo-adjust.php'),'public judge profile must not jump to a separate photo page');
console.log('Six-hour one-page judge profile update v472 checks passed.');
