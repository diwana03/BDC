'use strict';
const fs=require('fs'),assert=require('assert');
const read=path=>fs.readFileSync(path,'utf8');

const progression=read('app/Services/DivisionProgressionService.php');
const identity=read('app/Services/CompetitorIdentityService.php');
const registration=read('app/Services/GlobalScoringRegistrationHook.php');
const publicRegister=read('register/index.php');
const requests=read('admin/profile-requests/index.php');
const publications=[
 read('admin/scoring/publish.php'),
 read('admin/scoring/publish-salsa.php'),
 read('admin/scoring/special-publish.php'),
 read('admin/scoring/special-publish-salsa.php'),
];

for(const marker of [
 'approvedCareerState',
 'eligibilityFromApprovedHistory',
 "FROM bdc_participant_results WHERE competitor_id=:competitor AND dance_style=:dance",
 "FROM bdc_point_transactions WHERE competitor_id=:competitor AND dance_style=:dance",
 "initialDivisionForUnapprovedEntry",
])assert(progression.includes(marker),marker+' missing from approved-history progression service');
assert(!progression.includes('bdc_scoring_entries'),'draft/event entries must never establish career progression');
assert(!progression.includes('bdc_test_scoring_entries'),'Test entries must never establish career progression');

assert(identity.includes('$initialDivision=DivisionProgressionService::initialDivisionForUnapprovedEntry()'),'new scoring identities must start provisionally');
assert(registration.includes('SELECT event_id,division,dance_style FROM {$roundTable}'),'entry eligibility must be style-specific');
assert(registration.includes('eligibilityFromApprovedHistory'),'event entry must use approved history only');
assert(registration.includes("'entered_division'=>(string)$round['division']")&&registration.includes("'permanent_division_changed'=>false"),'entry audit must distinguish category from permanent division');
assert(registration.includes("$roundTable=$isTest?'bdc_test_scoring_rounds':'bdc_scoring_rounds'"),'Testing and Live registration must share the gate');
assert(registration.includes('mirrorOfficialToTest'),'Test identity mirror must remain active');

assert(publicRegister.includes('Competition category'),'website must not call an event category a permanent division');
assert(publicRegister.includes('This does not change your permanent BDC division'),'website must explain the approval rule');
assert(publicRegister.includes("'permanent_division_change_requested'=>false"),'website request must record non-progression intent');

assert(requests.includes("current_division) VALUES(:cid,:dance,:role,'novice')"),'new approved profile identity must remain provisional Novice');
assert(requests.includes("'permanent_division_changed'=>false"),'profile approval audit must preserve category without progression');
assert(!requests.includes("current_division=IF(VALUES(current_division)='unknown',current_division,VALUES(current_division))"),'website request must not overwrite an existing permanent division');
assert(requests.includes('Permanent division changes only after a Super Admin-approved competition result.'),'review screen must explain the gate');

for(const source of publications){
 assert(source.includes("if($action==='approve_publication')"),'publication must have explicit approval action');
 assert(/Only Super Admin can approve publication|Only Super Admin can approve/.test(source),'publication approval must require Super Admin');
 assert(source.includes('bdc_participant_results'),'approved publication must create official competition history');
 assert(source.includes('bdc_point_transactions'),'approved publication must create official point history');
}
console.log('Approved category progression v396 checks passed.');
