#!/usr/bin/env node
'use strict';
const fs=require('fs'),assert=require('assert');
const runner=fs.readFileSync('app/Services/MigrationRunner.php','utf8');
const version=JSON.parse(fs.readFileSync('VERSION.json','utf8'));
const migration='20260825_0100_repair_permanent_division_categories';
assert(runner.includes("'"+migration+"'"),'migration is not routed to stable file-only checksumming');
for(const checksum of [
 'df0325cd74ef31b3792a8abe3e01280a981317a92146a5ac680441773d1285ab',
 '4370bce0224d77c4718953fa425cc7fcf8e741bb1d2e3694379a4350db8c630d',
 '224b9199f9430cfc7d597aa90b5ef83013f39efd6e6765acc526aefc7a3b645f'
])assert(runner.includes(checksum),'known checksum missing: '+checksum);
assert(/^2\.3\.3-dev\d+$/.test(version.version)&&version.build>=3111,'VERSION.json predates dev405 build 3111');
console.log('PASS permanent-division migration checksum compatibility v405');
