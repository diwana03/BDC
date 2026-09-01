const assert = require('assert');
const fs = require('fs');

const read = file => fs.readFileSync(file, 'utf8');
const migration = read('database/migrations/20260901_0200_council_result_identities.php');
const identities = read('app/Services/CouncilResultIdentityService.php');
const cup = read('app/Services/DanceCupScoringService.php');
const results = read('results/index.php');
const special = read('app/Services/SpecialCategoryService.php');

for (const table of ['bdc_result_identities', 'bdc_wdc_identities', 'bdc_wdc_championship_points']) {
  assert(migration.includes(`CREATE TABLE IF NOT EXISTS ${table}`), `${table} migration is missing`);
}
assert(migration.includes("SELECT id,'bdc',bdc_id FROM bdc_competitors"), 'existing BDC identities must be preserved');
assert(migration.includes("dance_style='salsa'"), 'existing Salsa participants must receive SDC backfill');
assert(migration.includes("WHERE competition_level='open'"), 'historical WDC points backfill must be Open-only');
assert(migration.includes('WHEN 1 THEN 10 WHEN 2 THEN 8 WHEN 3 THEN 6 WHEN 4 THEN 4 WHEN 5 THEN 2 ELSE 1'), 'WDC backfill points schedule is wrong');

assert(identities.includes("return strtolower(trim($danceStyle))==='salsa'?'sdc':'bdc'"), 'dance-to-council isolation is missing');
assert(identities.includes("'SDC-'.str_pad"), 'SDC generator is missing');
assert(identities.includes("'WDC-'.str_pad"), 'WDC generator is missing');
assert(identities.includes("GET_LOCK('bdc-sdc-identity-sequence',10)"), 'SDC allocation must be concurrency locked');
assert(identities.includes("GET_LOCK('bdc-wdc-identity-sequence',10)"), 'WDC allocation must be concurrency locked');
assert(identities.includes('[1=>10,2=>8,3=>6,4=>4,5=>2]'), 'WDC Open points schedule is wrong');
assert(identities.includes('$placement>5?1:0'), 'valid WDC finishers after fifth must receive one point');

assert(cup.includes('CouncilResultIdentityService::wdcIdentityForEntry'), 'Live Dance Cup approval must assign a WDC identity');
assert(cup.includes("if((string)$row['competition_level']==='open')"), 'WDC championship points must be Open-only');
assert(cup.includes('ON DUPLICATE KEY UPDATE wdc_identity_id=VALUES(wdc_identity_id)'), 'Dance Cup publication must be idempotent');
assert(cup.includes('if(!$test){'), 'Test Dance Cup approval must not assign permanent identities or points');

assert(results.includes('FROM bdc_dance_cup_result_history'), 'public Dance Cup results must read official approval history');
assert(!results.includes('FROM bdc_dance_cup_results d'), 'public Dance Cup results must not read the legacy history source');
assert(results.includes("$council=$dance==='salsa'?'sdc':'bdc'"), 'public Bachata/Salsa results must select the correct council identity');
assert(results.includes('LOWER(ri.identity_code) LIKE LOWER'), 'result identity search must be case-insensitive');

for (const category of ['Bachata Invitational', 'Salsa Invitational']) assert(special.includes(category));
assert(special.includes('$invitational=[1=>10,2=>8,3=>6,4=>4,5=>2]'), 'Invitational points must be 10/8/6/4/2');

console.log('dev550 council result identity checks passed');
