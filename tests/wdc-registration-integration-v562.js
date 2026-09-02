const fs=require('fs');
const assert=(value,message)=>{if(!value)throw new Error(message)};
const service=fs.readFileSync('app/Services/ProfileIntegrationService.php','utf8');
const diagnostics=fs.readFileSync('app/Services/ProfileDiagnosticQueryService.php','utf8');
const review=fs.readFileSync('admin/integration-review/index.php','utf8');
const directory=fs.readFileSync('admin/dance-cup/competitors.php','utf8');
const migration=fs.readFileSync('database/migrations/20260902_0700_wdc_registration_integration.php','utf8');
const fixture=JSON.parse(fs.readFileSync('tests/fixtures/wdc-sbta-2026-registration-set.json','utf8'));

assert(fixture.identities.length===23,'SBTA WDC fixture must contain 23 permanent identities');
assert(fixture.identities.reduce((n,row)=>n+row.categories.length,0)===24,'SBTA WDC fixture must contain 24 unique category registrations');
assert(new Set(fixture.identities.map(row=>row.source_key)).size===23,'Every WDC source key must be unique');
assert(fixture.identities.filter(row=>row.display_name==='Bárbara Nicole Schumacher Alvear')[0].categories.length===2,'Bárbara must share one solo WDC identity across two categories');
assert(!fixture.identities.some(row=>/Hayan Jaguar/i.test(row.display_name)),'Incomplete Hayan partner entry must remain excluded');
assert(!fixture.identities.some(row=>row.entry_type==='couple'&&row.display_name==='Joan Teh'),'Incomplete Joan partner entries must remain excluded');
for(const row of fixture.identities)for(const category of row.categories)assert(fixture.categories[category].entry_type===row.entry_type,'Registration entry type must match its WDC identity');

for(const marker of ["'wdc_identity'",'wdcPayload','matchWdc','applyWdc','bdc_wdc_registrations','At least one WDC registration is required.','CouncilResultIdentityService::wdcIdentityForEntry'])assert(service.includes(marker),'Missing WDC integration marker: '+marker);
for(const marker of ["'wdc_members'","'person_match'","'read_only'=>true"])assert(diagnostics.includes(marker),'Missing read-only WDC diagnostic marker: '+marker);
assert(review.includes('entity=wdc_identity')&&review.includes("['competitor','wdc_identity']"),'WDC Super Admin review tab or permission gate missing');
assert(migration.includes("ENUM('competitor','judge','wdc_identity')")&&migration.includes('UNIQUE KEY uq_wdc_registration(event_key,wdc_identity_id,category_key)'),'Idempotent WDC staging schema missing');
assert(directory.includes('registration_count')&&directory.includes('registered_categories'),'WDC directory must expose approved registrations');
for(const forbidden of ['bdc_wdc_championship_points','bdc_dance_cup_result_history','bdc_participant_results','bdc_point_transactions'])assert(!new RegExp('(INSERT INTO|UPDATE|DELETE FROM)\\s+'+forbidden,'i').test(service),'WDC profile approval must not mutate scoring or result table '+forbidden);
console.log('WDC registration integration v562 checks passed');
