const fs=require('fs'),assert=require('assert');
const migration=fs.readFileSync('database/migrations/20260902_0200_create_missing_sdc_only_profiles.php','utf8');
for(const name of ['Sasa','SO YOUNG SHIN (Linda)','MITSUHIRO NAKAKOJI','Mika','Carlito','Lanye','Sharleen','Cookie'])assert(migration.includes(name),name+' missing');
for(const marker of ['VALUES(NULL',"council='sdc'",'CouncilResultIdentityService','salsa_rising','salsa_open',"current_division='unknown'",'beginTransaction','rollBack'])assert(migration.includes(marker),marker+' missing');
assert(!migration.includes("council='bdc'"),'must not create BDC identity');
assert(!migration.includes('UPDATE bdc_competitors'),'must not alter existing BDC records');
assert(!migration.includes('DELETE FROM bdc_competitors'),'must not delete BDC records');
console.log('missing SDC-only profiles v556 checks passed');
