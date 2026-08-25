#!/usr/bin/env node
'use strict';
const fs=require('fs'),assert=require('assert');
const service=fs.readFileSync('app/Services/UnapprovedProfileRepairService.php','utf8');
const runner=fs.readFileSync('app/Services/MigrationRunner.php','utf8');
const migration=fs.readFileSync('database/migrations/20260826_0400_apply_source_verified_profile_correction.php','utf8');
const version=JSON.parse(fs.readFileSync('VERSION.json','utf8'));
const unsupported=[
 'BDC-000446','BDC-000208','BDC-000205','BDC-000492','BDC-000315','BDC-000501','BDC-000309','BDC-000355','BDC-000398','BDC-000295','BDC-000468','BDC-000216','BDC-000410','BDC-000290','BDC-000345','BDC-000287','BDC-000477','BDC-000272','BDC-000236','BDC-000337','BDC-000363','BDC-000391','BDC-000214','BDC-000288','BDC-000400','BDC-000245','BDC-000438'
];
const protectedIds=['BDC-000453','BDC-000457','BDC-000428','BDC-000415','BDC-000454','BDC-000496'];
for(const id of unsupported)assert(service.includes("'bdc_id'=>'"+id+"'"),id+' missing from exact correction');
for(const id of protectedIds)assert(!service.includes("'bdc_id'=>'"+id+"'"),id+' must not be in exact correction allowlist');
assert((service.match(/\['bdc_id'=>'BDC-/g)||[]).length===27,'allowlist must contain exactly 27 profiles');
for(const token of ['repairConfirmedUnsupportedProfiles','createDatabaseBackup','strcasecmp','published_or_manual_evidence','recovery_history_retained'])assert(service.includes(token),token+' safety missing');
assert(migration.includes('repairConfirmedUnsupportedProfiles'),'migration does not call exact correction');
assert(runner.includes('20260826_0400_apply_source_verified_profile_correction'),'migration checksum mode missing');
assert(version.build>=3116&&/^2\.3\.3-dev\d+$/.test(version.version),'VERSION.json predates dev410 build 3116');
console.log('PASS source-verified exact profile correction v410');
