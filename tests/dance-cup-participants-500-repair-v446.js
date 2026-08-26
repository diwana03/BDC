const fs=require('fs');
const source=fs.readFileSync('admin/dance-cup/participants.php','utf8');
const version=JSON.parse(fs.readFileSync('VERSION.json','utf8'));
const assert=(ok,message)=>{if(!ok)throw new Error(message)};
assert(!source.includes('bdc_dance_cup_competitions c JOIN bdc_events e'),'general J&J event table leaked into Dance Cup Participants');
assert(source.includes('href="approvals.php"'),'separate Dance Cup approval route missing');
assert(source.includes("catch(Throwable $exception){$error='Some Dance Cup dashboard data could not be loaded:"),'safe dashboard failure boundary missing');
assert(Number((version.version.match(/dev(\d+)$/)||[])[1])>=446&&version.build>=3152,'release version predates dev446');
console.log('Dance Cup Participants 500 repair v446 checks passed.');
