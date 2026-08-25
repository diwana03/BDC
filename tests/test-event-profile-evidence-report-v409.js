#!/usr/bin/env node
'use strict';
const fs=require('fs'),assert=require('assert');
const service=fs.readFileSync('app/Services/UnapprovedProfileRepairService.php','utf8');
const page=fs.readFileSync('admin/competitors/test-event-profile-report.php','utf8');
const index=fs.readFileSync('admin/competitors/index.php','utf8');
const version=JSON.parse(fs.readFileSync('VERSION.json','utf8'));
for(const token of ['public static function diagnostic','test_event_only_candidate','published_protected','manual_history_protected','recovery_evidence_review',"if($userId<1)"])assert(service.includes(token),token+' diagnostic/safety missing');
assert(page.includes('READ ONLY')&&page.includes('Nothing is changed from this page'),'report is not clearly non-destructive');
assert(index.includes('test-event-profile-report.php'),'report is not linked from Competitors');
assert(version.version==='2.3.3-dev409'&&version.build===3115,'VERSION.json is not dev409 build 3115');
console.log('PASS read-only named test-event profile evidence report v409');
