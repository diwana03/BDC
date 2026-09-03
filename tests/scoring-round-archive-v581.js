const fs=require('fs');const assert=require('assert');
const endpoint=fs.readFileSync('admin/scoring/archive-round.php','utf8');const live=fs.readFileSync('admin/scoring/active-dashboard.php','utf8');const test=fs.readFileSync('admin/scoring-tests/index.php','utf8');const migration=fs.readFileSync('database/migrations/20260903_0200_scoring_round_archive_restore.php','utf8');
assert(endpoint.includes("Auth::isSuperAdmin()"),'Archive/restore must be Super Admin-only');
assert(endpoint.includes("archived_from_status=status,status='archived'"),'Archive must preserve the previous status');
assert(endpoint.includes("status=:status,archived_from_status=NULL"),'Restore must recover the previous status');
assert(endpoint.includes("['bdc_test_scoring_rounds':'bdc_scoring_rounds'")===false,'Table selection must not use unsafe request interpolation');
for(const table of ['bdc_scoring_rounds','bdc_test_scoring_rounds'])assert(migration.includes(table),`Missing archive columns for ${table}`);
for(const page of [live,test]){assert(page.includes('Archived Rounds'),'Archived view missing');assert(page.includes('archive-round.php'),'Archive endpoint missing');assert(page.includes('Restore'),'Restore action missing');}
for(const forbidden of ['DELETE FROM bdc_scoring_rounds','DELETE FROM bdc_test_scoring_rounds','DELETE FROM bdc_scoring_marks','DELETE FROM bdc_test_scoring_marks'])assert(!endpoint.includes(forbidden),`Archive must never delete data: ${forbidden}`);
console.log('Scoring round archive v581 tests passed.');
