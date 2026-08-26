const fs=require('fs');
const source=fs.readFileSync('admin/dance-cup/participants.php','utf8');
const version=JSON.parse(fs.readFileSync('VERSION.json','utf8'));
const assert=(ok,message)=>{if(!ok)throw new Error(message)};
for(const column of ['competitor_id','event_id','placement','approved_at'])assert(source.includes("$columnExists('bdc_dance_cup_result_history','"+column+"')"),'missing history readiness check: '+column);
assert(source.includes('if($historyReady){'),'participant query is not isolated from optional history');
assert(Number((version.version.match(/dev(\d+)$/)||[])[1])>=447&&version.build>=3153,'release predates dev447');
console.log('Dance Cup partial-history participant recovery v447 checks passed.');
