const fs=require('fs');
const service=fs.readFileSync('app/Services/ProfileIntegrationService.php','utf8');
const panel=fs.readFileSync('admin/integration-review/index.php','utf8');
const assert=(condition,message)=>{if(!condition)throw new Error(message)};

for(const marker of [
  "$operation==='photo_replace'",
  "['operation','wdc_id','photo_base64','photo_mime','photo_name']",
  'WDC photo replacement requires a valid WDC ID.',
  'WDC photo replacement requires an original JPG, PNG or WebP image.',
  "SELECT id FROM bdc_wdc_identities WHERE identity_code=:code AND status='active'",
  "hash_equals((string)$identity['identity_code'],(string)$p['wdc_id'])",
  "publishPhoto($u,'wdc','wdc-'.$id)",
  'UPDATE bdc_wdc_identities SET photo_url=:photo WHERE id=:id',
])assert(service.includes(marker),'missing WDC photo-only safeguard: '+marker);

const applyStart=service.indexOf("if(($p['operation']??'upsert')==='photo_replace'){",service.indexOf('private static function applyWdc'));
const applyEnd=service.indexOf("if(!$identity){",applyStart);
assert(applyStart>0&&applyEnd>applyStart,'WDC photo-only approval branch is missing');
const photoBranch=service.slice(applyStart,applyEnd);
for(const forbidden of ['bdc_wdc_registrations','bdc_competitors','bdc_competitor_discipline_profiles','display_name=:name','country=:country'])assert(!photoBranch.includes(forbidden),'photo-only approval touches forbidden data: '+forbidden);
assert(photoBranch.includes("return ['id'=>$id,'new_photo_path'=>$path]"),'photo-only approval must return before the full WDC upsert');
assert(panel.includes("$payload['wdc_id']??'Unnamed'"),'WDC photo-only review must identify the exact WDC record');

console.log('WDC photo-only integration API v579 checks passed.');
