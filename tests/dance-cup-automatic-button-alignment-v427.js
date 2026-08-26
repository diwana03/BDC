'use strict';
const fs=require('fs'),assert=require('assert');
const setup=fs.readFileSync('admin/dance-cup/automatic-setup.php','utf8');
assert(setup.includes('form.row{align-items:start!important}'),'Automatic add controls must share the input top baseline');
assert(setup.includes('form.row>.col-auto:last-child>.btn{height:38px!important'),'Automatic Add buttons must use the input height');
assert(setup.includes('.form-check{height:38px!important'),'Chief control must align with the judge input and Add button');
assert(setup.includes('.dc-roster-actions .btn{height:32px!important'),'Roster action buttons must share one height');
assert(setup.includes('.dc-auto-directory-grid + section .btn-lg{height:46px!important'),'Unified workspace shortcut and Live Projection must share one height');
console.log('PASS Dance Cup Automatic button alignment v427');
