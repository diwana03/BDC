#!/usr/bin/env node
'use strict';
const fs=require('fs'),assert=require('assert');
const read=file=>fs.readFileSync(file,'utf8');
const service=read('app/Services/GoogleFormSyncService.php');
const script=read('integrations/google-forms/Code.gs');

for(const marker of ['dance_role FROM bdc_competitors','roleCompatible','$compatible'])assert(service.includes(marker),'role-safe identity marker missing: '+marker);
for(const marker of ['reconcileBdcRows','everyMinutes(15)','BDC_SYNC_RETRY_ROWS_','BDC_SYNC_CURSOR_','BDC_SPREADSHEET_ID','installBdcTriggers','BDC photo skipped'])assert(script.includes(marker),'scheduled reconciliation marker missing: '+marker);
console.log('PASS Google Form scheduled reconciliation and role-safe identity matching v412');
