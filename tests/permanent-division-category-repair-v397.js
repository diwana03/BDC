#!/usr/bin/env node
const fs=require('fs');
const path=require('path');
const root=path.resolve(__dirname,'..');
const read=(file)=>fs.readFileSync(path.join(root,file),'utf8');
const requireText=(source,needle,label)=>{if(!source.includes(needle))throw new Error(`${label}: missing ${needle}`)};
const forbidText=(source,needle,label)=>{if(source.includes(needle))throw new Error(`${label}: forbidden ${needle}`)};

const division=read('app/Services/DivisionProgressionService.php');
const migration=read('database/migrations/20260825_0100_repair_permanent_division_categories.php');
const hook=read('app/Services/GlobalScoringRegistrationHook.php');
const formSync=read('app/Services/GoogleFormSyncService.php');
const editor=read('admin/competitors/edit.php');
const release=read('RELEASE-v2.3.3-dev397.md');
const version=JSON.parse(read('VERSION.json'));

for(const needle of [
  'approvedPermanentDivision',
  'repairLegacySpecialCategoryAssignments',
  "bdc_competitor_discipline_profiles WHERE current_division IN",
  "bdc_competitors WHERE current_division IN",
  "bdc_test_competitors WHERE current_division IN",
  "competed_all_star",
  "competed_advanced",
  "competed_intermediate",
  "['semi_pro','pro','professional']",
])requireText(division,needle,'approved-history repair');

for(const table of ['bdc_competitors','bdc_competitor_discipline_profiles','bdc_test_competitors'])requireText(migration,table,'migration identity table');
requireText(migration,'repairLegacySpecialCategoryAssignments','migration invokes repair');
requireText(migration,"'novice','intermediate','advanced','semi_pro','pro','professional','all_star','unknown'",'permanent enum');
for(const special of ['bachata_rising','bachata_open','bachata_invitational','salsa_rising','salsa_open'])forbidText(migration,special,'permanent enum special category');

requireText(formSync,"VALUES(:id,:style,:role,'novice')",'Google Form provisional profile');
requireText(formSync,'requested_categories','Google Form event category retention');
forbidText(formSync,'current_division=VALUES(current_division)','Google Form permanent overwrite');
forbidText(formSync,'SET dance_role=:role,current_division=:division','Google Form legacy overwrite');

for(const route of ['automatic-setup-action','discipline-actions'])requireText(hook,route,'shared registration route');
requireText(hook,'if(!$create)','existing competitor approved-history gate');
requireText(hook,'eligibilityFromApprovedHistory','approved eligibility');
requireText(hook,'initialDivisionForUnapprovedEntry','provisional new identity');
forbidText(hook,'if(!$isDesk&&!$create)return','legacy create-only bypass');

for(const special of ['bachata_rising','bachata_open','bachata_invitational','salsa_rising','salsa_open'])forbidText(editor,special,'permanent competitor editor');

if(!/^2\.3\.3-dev\d+$/.test(version.version)||version.build<3103)throw new Error('VERSION.json predates dev397 build 3103');
requireText(release,'Production: **blocked','release deployment gate');
console.log('PASS permanent division category repair v397');
