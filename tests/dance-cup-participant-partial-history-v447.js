const fs=require('fs');
const source=fs.readFileSync('admin/dance-cup/participants.php','utf8');
const version=JSON.parse(fs.readFileSync('VERSION.json','utf8'));
const assert=(ok,message)=>{if(!ok)throw new Error(message)};
assert(!source.includes('bdc_dance_cup_result_history'),'optional history must be fully isolated from participant management');
assert(source.includes("r.status='registered'"),'non-approved WDC registrations must stay outside participant management');
assert(Number((version.version.match(/dev(\d+)$/)||[])[1])>=447&&version.build>=3153,'release predates dev447');
console.log('Dance Cup partial-history participant recovery v447 checks passed.');
