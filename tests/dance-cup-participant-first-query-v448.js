const fs=require('fs');
const source=fs.readFileSync('admin/dance-cup/participants.php','utf8');
const version=JSON.parse(fs.readFileSync('VERSION.json','utf8'));
const assert=(ok,message)=>{if(!ok)throw new Error(message)};
assert(source.includes('bdc_competitor_dance_cup_profiles p JOIN bdc_competitors'),'approved participant query missing');
assert(!source.includes('$historyJoin='),'optional history is still joined into the core participant query');
assert(source.includes('if($historyReady){'),'history count is not isolated');
assert(Number((version.version.match(/dev(\d+)$/)||[])[1])>=448&&version.build>=3154,'release predates dev448');
console.log('Dance Cup participant-first query v448 checks passed.');
