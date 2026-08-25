#!/usr/bin/env node
'use strict';
const fs=require('fs'),assert=require('assert');
const service=fs.readFileSync('app/Services/UnapprovedProfileRepairService.php','utf8');
const migration=fs.readFileSync('database/migrations/20260826_0300_repair_named_test_event_profiles.php','utf8');
const runner=fs.readFileSync('app/Services/MigrationRunner.php','utf8');
const version=JSON.parse(fs.readFileSync('VERSION.json','utf8'));
for(const name of ['BDC LIVE PARITY TEST - DO NOT PUBLISH','4th ASIA Open SALSA JACK & JILL COMPETITION 2026',"TEST EVENT 2 - Michael''s Imaginary J&J Event",'1st Asia Amateur Salsa Jack & Jill Competition','4th ASIA Open BACHATA JACK & JILL COMPETITION 2026','1st Asia Amateur Bachata Jack & Jill Competition','SBTA Bachata Rising','BASS × Timba Tropical Collaboration'])assert(service.includes(name),name+' target missing');
for(const token of ['JOIN bdc_events e','targetEventsSql','$targetEvidence',"pr.source IN('historical_import','manual')","pt.source_type IN('manual','csv_import','correction')","sp.status='published'",'bdc_special_category_recovery'])assert(service.includes(token),token+' scope/protection missing');
assert(migration.includes('UnapprovedProfileRepairService::repair($pdo,0)'),'targeted migration repair missing');
assert(runner.includes("'20260826_0300_repair_named_test_event_profiles'"),'targeted migration lacks stable wrapper checksum');
assert(version.version==='2.3.3-dev408'&&version.build===3114,'VERSION.json is not dev408 build 3114');
console.log('PASS named test-event-only profile repair v408');
