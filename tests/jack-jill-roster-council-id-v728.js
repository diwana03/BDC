const fs=require('fs');
const assert=require('assert');

const live=fs.readFileSync('admin/scoring/automatic-common-setup.php','utf8');
const test=fs.readFileSync('admin/scoring-tests/index.php','utf8');
const version=JSON.parse(fs.readFileSync('VERSION.json','utf8'));

assert(live.includes("$roundDance=JackJillCompetitorEligibilityService::dance"),'Live Automatic setup must normalize the round dance before resolving identity');
assert(live.includes("SELECT s.sdc_id FROM bdc_sdc_competitors"),'Live Salsa rosters must resolve the official SDC identity');
assert(live.includes("'c.bdc_id'"),'Live Bachata rosters must retain the official BDC identity');
assert(live.includes('{$identitySelect} identity_code'),'Live roster query must expose a council-neutral identity field');
assert(live.includes("<th>'.$council.' ID</th>"),'Live roster heading must name the active council');
assert(live.includes("$entry['identity_code']"),'Live roster rows must render the council-neutral identity field');
assert(live.includes("'Missing '.$council.' ID'"),'Live roster must visibly flag missing council identities instead of showing a blank');

assert((test.match(/<th><\?=e\(\$testCouncil\)\?> ID<\/th>/g)||[]).length===2,'Test Leader and Follower tables must name the active council');
assert((test.match(/'Missing '\.\$testCouncil\.' ID'/g)||[]).length===2,'Test rosters must visibly flag missing council identities');
assert(version.version==='2.3.6-dev728'&&version.build===3434,'release metadata mismatch');

console.log('dev728 Jack & Jill council identity checks passed');
