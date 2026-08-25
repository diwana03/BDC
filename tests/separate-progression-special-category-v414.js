#!/usr/bin/env node
'use strict';
const fs=require('fs'),assert=require('assert');
const files={
 migration:fs.readFileSync('database/migrations/20260826_0600_separate_special_categories.php','utf8'),
 verified:fs.readFileSync('database/migrations/20260826_0500_publish_verified_form_profiles.php','utf8'),
 recovery:fs.readFileSync('app/Services/SpecialCategoryRecoveryService.php','utf8'),
 list:fs.readFileSync('admin/competitors/index.php','utf8'),
 edit:fs.readFileSync('admin/competitors/edit.php','utf8'),
 merge:fs.readFileSync('admin/competitors/merge.php','utf8'),
 results:fs.readFileSync('results/index.php','utf8')
};
for(const key of ['migration','verified','recovery','list','edit','merge','results'])assert(files[key].includes('special_category'),key+' is not wired to separate Special Category storage');
assert(files.migration.includes('approvedPermanentDivision'),'legacy Special Categories must restore calculated career progression');
assert(!/current_division\) VALUES\([^\n]+:division\)/.test(files.verified),'verified form publication must not overwrite career division');
assert(files.verified.includes('special_category=VALUES(special_category)'),'verified manifest must publish the separate category');
assert(files.list.includes('dp.current_division=:division OR dp.special_category=:division'),'Competitor Management must filter both axes');
for(const label of ['Novice:','Intermediate:','Advanced:','Total ','Current level:','Special Competition Category:'])assert(files.results.includes(label),'results summary missing '+label);
assert(files.edit.includes('Current progression level')&&files.edit.includes('Special Competition Category'),'admin editor must expose separate fields');
assert(files.merge.includes('bdc_form_sync_submissions')&&files.merge.includes('bdc_competitor_discipline_profiles'),'duplicate merge must preserve form submissions and both profile axes');
assert(!files.recovery.includes("$legacy->execute"),'Special Category recovery must never overwrite legacy progression');
console.log('PASS separate career progression, point breakdown and Special Category v414');
