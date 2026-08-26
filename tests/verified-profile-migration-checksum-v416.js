#!/usr/bin/env node
'use strict';
const fs=require('fs'),crypto=require('crypto'),assert=require('assert');
const migration=fs.readFileSync('database/migrations/20260826_0500_publish_verified_form_profiles.php');
const runner=fs.readFileSync('app/Services/MigrationRunner.php','utf8');
const fileHash=crypto.createHash('sha256').update(migration).digest('hex');
const stable=crypto.createHash('sha256').update(fileHash).digest('hex');
assert.strictEqual(stable,'064f1c3a9332383b301663ad43000a088af5e1f45d2bc364c8a572c69902dfd8','immutable 0500 file-only checksum changed');
for(const token of [
 '20260826_0500_publish_verified_form_profiles',
 '05879cf08f3131f0a33c0ec38ada73b5e8a08481602ec4c662fb8e887c6dab31',
 stable
])assert(runner.includes(token),'MigrationRunner missing exact 0500 compatibility token '+token);
assert((runner.match(/05879cf08f3131f0a33c0ec38ada73b5e8a08481602ec4c662fb8e887c6dab31/g)||[]).length===1,'stored checksum must have one narrow compatibility entry');
console.log('PASS verified-profile immutable migration checksum compatibility v416');
