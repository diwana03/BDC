const fs=require('fs');
const source=fs.readFileSync('admin/dance-cup/participants.php','utf8');
const version=JSON.parse(fs.readFileSync('VERSION.json','utf8'));
const assert=(ok,message)=>{if(!ok)throw new Error(message)};
assert(source.includes('JOIN bdc_dance_cup_events e ON e.id=c.event_id'),'Dance Cup approval query still uses the wrong event table');
assert(!source.includes('bdc_dance_cup_competitions c JOIN bdc_events e'),'general J&J event table leaked into Dance Cup Participants');
assert(source.includes("catch(Throwable $exception){$error='Dance Cup participant data could not be loaded:"),'safe dashboard failure boundary missing');
assert(version.version==='2.3.3-dev446'&&version.build===3152,'release version mismatch');
console.log('Dance Cup Participants 500 repair v446 checks passed.');
