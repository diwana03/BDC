const fs=require('fs'),assert=require('assert');
const service=fs.readFileSync('app/Services/JudgeRegistrationLinkService.php','utf8');
const admin=fs.readFileSync('admin/judges/index.php','utf8');
const page=fs.readFileSync('judge-profile/index.php','utf8');
for(const marker of ['INTERVAL 12 HOUR','token_hash','expires_at>NOW()','https://','?invite='])assert(service.includes(marker),marker);
for(const marker of ['generate_registration_link','Copy Full Link','12-hour Full Link','registrationLink'])assert(admin.includes(marker),marker);
for(const marker of ['invite_token','JudgeRegistrationLinkService::valid','new 12-hour full link'])assert(page.includes(marker)||marker==='invite_token'&&page.includes("$_GET['invite']"),marker);
assert(!admin.includes("$publicLink=url('judge-profile/');"),'generic registration link must be removed');
console.log('Token-protected 12-hour full judge registration link v473 checks passed.');
