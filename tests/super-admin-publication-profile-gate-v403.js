#!/usr/bin/env node
'use strict';
const fs=require('fs'),assert=require('assert');
const read=file=>fs.readFileSync(file,'utf8');
const hook=read('app/Services/GlobalScoringRegistrationHook.php');
const identity=read('app/Services/CompetitorIdentityService.php');
const forms=read('app/Services/GoogleFormSyncService.php');
const automatic=read('admin/scoring/automatic-setup-action.php');
const discipline=read('admin/scoring/discipline-actions.php');
const progression=read('app/Services/DivisionProgressionService.php');
const publish=read('admin/scoring/publish.php');
const salsa=read('admin/scoring/publish-salsa.php');
const repair=read('app/Services/UnapprovedProfileRepairService.php');

assert(identity.includes('findOrCreateTest'),'Test-only identity path missing');
assert(hook.includes('mirrorOfficialToTest'),'Test lookup must mirror only an eligible official council identity');
assert(!hook.includes('if(!empty($competitor[\'created\'])){$pdo->prepare("INSERT INTO bdc_competitor_discipline_profiles'),'roster creation still writes a permanent profile');
assert(!forms.includes('upsertProfile($pdo'),'Google Form registration still writes a permanent profile');
assert(!automatic.includes('INSERT INTO bdc_competitor_discipline_profiles'),'Automatic roster still writes a permanent profile');
assert(!discipline.includes("$pdo->prepare('INSERT INTO bdc_competitor_discipline_profiles"),'Salsa roster still writes a permanent profile');
assert(progression.includes('syncProfileAfterApproval'),'approval profile synchronizer missing');
assert(publish.includes("syncProfileAfterApproval($pdo,$person['competitor_id'],$person['dance_role'],'bachata')"),'Bachata approval gate missing');
assert(salsa.includes("syncProfileAfterApproval($pdo,$person['c'],$person['role'],'salsa')"),'Salsa approval gate missing');
for(const marker of ['createDatabaseBackup','NOT EXISTS','bdc_participant_results','bdc_point_transactions','bdc_test_scoring_entries','bdc_scoring_entries'])assert(repair.includes(marker),marker+' missing from old-data repair');
console.log('PASS Super Admin publication profile gate v403');
