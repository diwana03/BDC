#!/usr/bin/env node
'use strict';
const fs=require('fs'),assert=require('assert');
const migration=fs.readFileSync('database/migrations/20260826_0500_publish_verified_form_profiles.php','utf8');
const entries=[...migration.matchAll(/\['bdc_id'=>'(BDC-\d+)','name'=>'([^']+)','dance'=>'(bachata|salsa)','role'=>'(leader|follower)','division'=>'(bachata_rising|bachata_open|salsa_rising|salsa_open)'\]/g)].map(m=>m.slice(1));
assert.strictEqual(entries.length,29,'28 people must produce exactly 29 style profiles');
assert.strictEqual(new Set(entries.map(r=>r[0])).size,28,'manifest must contain exactly 28 unique BDC identities');
const totals=entries.reduce((a,r)=>(a[r[4]]=(a[r[4]]||0)+1,a),{});
assert.deepStrictEqual(totals,{bachata_open:11,bachata_rising:8,salsa_rising:1,salsa_open:9});
for(const mapping of [["BDC-000549","BDC-000424"],["BDC-000543","BDC-000523"],["BDC-000528","BDC-000522"]])for(const id of mapping)assert(migration.includes("'"+id+"'"),id+' duplicate mapping missing');
for(const token of ['createDatabaseBackup','beginTransaction','bdc_form_sync_submissions','bdc_competitor_discipline_profiles','bdc_special_category_recovery','normaliseCompetitorName'])assert(migration.includes(token),token+' safety missing');
const mamong=entries.filter(r=>r[0]==='BDC-000540');assert.strictEqual(mamong.length,2,'MAMONG must retain both Bachata and Salsa Open profiles');
console.log('PASS verified 28-person form profile publication and duplicate consolidation v413');
