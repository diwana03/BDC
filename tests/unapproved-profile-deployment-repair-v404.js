#!/usr/bin/env node
'use strict';
const fs=require('fs'),assert=require('assert');
const migration=fs.readFileSync('database/migrations/20260825_0500_remove_unapproved_salsa_profiles.php','utf8');
const service=fs.readFileSync('app/Services/UnapprovedProfileRepairService.php','utf8');
const runner=fs.readFileSync('app/Services/MigrationRunner.php','utf8');
const version=JSON.parse(fs.readFileSync('VERSION.json','utf8'));

assert(migration.includes('UnapprovedProfileRepairService::repair($pdo,0)'),'deployment does not execute the historical repair');
assert(migration.includes('BackupService.php'),'deployment repair is not tied to the backup dependency');
assert(runner.includes("'20260825_0500_remove_unapproved_salsa_profiles'"),'reusable repair migration must keep a stable file-only checksum');
for(const marker of ['createDatabaseBackup','bdc_unapproved_profile_repairs','bdc_participant_results','bdc_point_transactions','beginTransaction','rollBack'])assert(service.includes(marker),marker+' safety missing');
assert(service.includes("p.current_division IN('novice','intermediate','advanced','semi_pro','pro','professional','all_star','unknown')"),'Special Categories are not explicitly excluded');
assert(/^2\.3\.3-dev\d+$/.test(version.version)&&version.build>=3110,'VERSION.json predates dev404 build 3110');
console.log('PASS automatic protected old-profile deployment repair v404');
