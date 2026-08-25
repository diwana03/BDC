#!/usr/bin/env node
'use strict';
const fs=require('fs'),assert=require('assert');
const service=fs.readFileSync('app/Services/UnapprovedProfileRepairService.php','utf8');
const migration=fs.readFileSync('database/migrations/20260826_0100_remove_unpublished_salsa_special_categories.php','utf8');
const runner=fs.readFileSync('app/Services/MigrationRunner.php','utf8');
const version=JSON.parse(fs.readFileSync('VERSION.json','utf8'));
for(const token of ["'salsa_rising','salsa_open'",'bdc_special_category_recovery','scr.recovered_category=p.current_division','scr.applied_at IS NOT NULL','$specialEvidence','bdc_participant_results','bdc_point_transactions','createDatabaseBackup'])assert(service.includes(token),token+' protection missing');
assert(migration.includes('UnapprovedProfileRepairService::repair($pdo,0)'),'second-pass deployment repair missing');
assert(runner.includes("'20260826_0100_remove_unpublished_salsa_special_categories'"),'second-pass migration lacks stable wrapper checksum');
assert(/^2\.3\.3-dev\d+$/.test(version.version)&&version.build>=3112,'VERSION.json predates dev406 build 3112');
console.log('PASS unpublished Salsa Special Category repair v406');
