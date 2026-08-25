#!/usr/bin/env node
'use strict';
const fs=require('fs'),assert=require('assert');
const service=fs.readFileSync('app/Services/UnapprovedProfileRepairService.php','utf8');
const migration=fs.readFileSync('database/migrations/20260826_0200_remove_all_unpublished_event_profiles.php','utf8');
const runner=fs.readFileSync('app/Services/MigrationRunner.php','utf8');
const danceCup=fs.readFileSync('admin/dance-cup/category.php','utf8')+fs.readFileSync('app/Services/DanceCupScoringService.php','utf8');
const version=JSON.parse(fs.readFileSync('VERSION.json','utf8'));
for(const token of ["p.dance_style IN('bachata','salsa')",'pr.dance_style=p.dance_style','pt.dance_style=p.dance_style','scr.dance_style=p.dance_style',"$dance==='bachata'",'$clearLegacy'])assert(service.includes(token),token+' cross-dance protection missing');
assert(migration.includes('UnapprovedProfileRepairService::repair($pdo,0)'),'complete deployment repair missing');
assert(runner.includes("'20260826_0200_remove_all_unpublished_event_profiles'"),'complete repair lacks stable wrapper checksum');
assert(!danceCup.includes('bdc_competitor_discipline_profiles'),'Dance Cup roster must not mutate J&J discipline profiles');
assert(/^2\.3\.3-dev\d+$/.test(version.version)&&version.build>=3113,'VERSION.json predates dev407 build 3113');
console.log('PASS complete unpublished event profile repair v407');
