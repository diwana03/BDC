#!/usr/bin/env node
'use strict';
const fs=require('fs'),assert=require('assert');
const page=fs.readFileSync('admin/competitors/index.php','utf8');
for(const style of ['bachata','salsa'])for(const division of ['novice','intermediate','advanced'])assert(page.includes(style+'_'+division+'_points'),style+' '+division+' aggregation missing');
for(const label of [' Total:','Novice:','Intermediate:','Advanced:'])assert(page.includes(label),'dashboard point label missing '+label);
assert(page.includes("dance_style='bachata' AND division='novice'"),'Bachata breakdown must be style-specific');
assert(page.includes("dance_style='salsa' AND division='advanced'"),'Salsa breakdown must be style-specific');
console.log('PASS Competitor Management per-style point breakdown v418');
