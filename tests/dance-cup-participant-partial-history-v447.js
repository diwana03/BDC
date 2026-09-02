const fs=require('fs');
const source=fs.readFileSync('admin/dance-cup/competitors.php','utf8');
const version=JSON.parse(fs.readFileSync('VERSION.json','utf8'));
const assert=(ok,message)=>{if(!ok)throw new Error(message)};
assert(source.includes('LEFT JOIN bdc_wdc_registrations r'),'active registration summary must use WDC registration ledger');
assert(source.includes("status='registered'"),'withdrawn WDC registrations must stay outside active registration display');
assert(Number((version.version.match(/dev(\d+)$/)||[])[1])>=447&&version.build>=3153,'release predates dev447');
console.log('Dance Cup partial-history participant recovery v447 checks passed.');
