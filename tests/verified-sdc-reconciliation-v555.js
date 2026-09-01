const fs=require('fs'),assert=require('assert');
const migration=fs.readFileSync('database/migrations/20260902_0100_reconcile_verified_sdc_profiles.php','utf8');
for(const marker of ['bdc_sdc_duplicate_resolution_archive','bdc_sdc_association_removal_archive','CouncilResultIdentityService','salsa_rising','salsa_open',"current_division='unknown'",'bdc_participant_results','bdc_point_transactions','beginTransaction','rollBack'])assert(migration.includes(marker),marker+' missing');
for(const id of [487,415,443,572,576,537,578,523,566,567])assert(migration.includes(String(id)),'target '+id+' missing');
assert(!migration.includes('DELETE FROM bdc_competitors'),'BDC competitor rows must remain');
assert(!migration.includes("council='bdc'"),'BDC result identities must remain');
assert(!migration.includes("dance_style='bachata'"),'Bachata data must remain');
console.log('verified SDC reconciliation v555 checks passed');
