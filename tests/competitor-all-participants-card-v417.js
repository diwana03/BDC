#!/usr/bin/env node
'use strict';
const fs=require('fs'),assert=require('assert');
const page=fs.readFileSync('admin/competitors/index.php','utf8');
for(const token of [
 "'all_participants'=>",
 'SELECT COUNT(*) FROM bdc_result_identities',
 "$hasListFilters=",
 "$isAll=$key==='all_participants'",
 "$isActive=$isAll?!$hasListFilters:$filter===$key",
 "$isAll?e(queryUrl(['filter'=>null,'page'=>1]))",
 'summary-grid'
])assert(page.includes(token),'All Participants dashboard card missing '+token);
const counts=page.slice(page.indexOf("'all_participants'=>"),page.indexOf('];',page.indexOf("'all_participants'=>")));
assert(counts.indexOf("'all_participants'=>")<counts.indexOf("'missing_photo'"),'All Participants must be the first summary card');
console.log('PASS Competitor Management All Participants reset card v417');
