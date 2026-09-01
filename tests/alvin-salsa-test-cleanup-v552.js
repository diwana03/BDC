const assert=require('assert');
const fs=require('fs');
const migration=fs.readFileSync('database/migrations/20260901_0400_remove_alvin_test_salsa_profile.php','utf8');

assert(migration.includes("bdc_id='BDC-000248'"),'cleanup must target Alvin by permanent BDC ID');
assert(migration.includes("exact_name='Alvin Foo Dun Zhi'"),'cleanup must also verify Alvin exact name');
assert(migration.includes("bdc_point_transactions WHERE competitor_id=:competitor AND dance_style='salsa'"),'cleanup must protect official Salsa points');
assert(migration.includes("bdc_participant_results WHERE competitor_id=:competitor AND dance_style='salsa'"),'cleanup must protect official Salsa results');
assert(migration.includes('bdc_competitor_discipline_profiles'),'testing Salsa profile cleanup missing');
assert(migration.includes('bdc_competitor_special_categories'),'testing Salsa categories cleanup missing');
assert(migration.includes("council='sdc'"),'testing SDC identity cleanup missing');
assert(!migration.includes("dance_style='bachata'"),'cleanup must not touch Bachata data');
assert(migration.includes('beginTransaction()')&&migration.includes('rollBack()'),'cleanup must be atomic');
console.log('dev552 Alvin Salsa test cleanup checks passed');
