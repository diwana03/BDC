const fs=require('fs'),assert=require('assert');
const migration=fs.readFileSync('database/migrations/20260901_0500_remove_nonregistered_sdc_associations.php','utf8');
for(const id of [380,293,325,241,291,396,260,261])assert(migration.includes(String(id)),'missing confirmed competitor '+id);
for(const marker of ['bdc_sdc_association_removal_archive',"dance_style='salsa'","council='sdc'",'bdc_participant_results','bdc_point_transactions','beginTransaction','rollBack','hash_equals'])assert(migration.includes(marker),marker+' safety missing');
assert(migration.includes('identity_json')&&migration.includes('profile_json')&&migration.includes('categories_json'),'recoverable archive incomplete');
assert(!migration.includes("DELETE FROM bdc_competitors"),'competitor identity must be preserved');
assert(!migration.includes("dance_style='bachata'"),'Bachata data must not be touched');
console.log('nonregistered SDC removal v554 checks passed');
