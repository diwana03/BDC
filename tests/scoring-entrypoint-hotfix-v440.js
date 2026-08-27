const fs=require('fs');
const assert=require('assert');

const core=fs.readFileSync('admin/scoring/core.php','utf8');
const version=JSON.parse(fs.readFileSync('VERSION.json','utf8'));

const releaseNumber=Number(version.version.match(/^2\.3\.3-dev(\d+)$/)?.[1]||0);
assert(releaseNumber>=440,'release must retain the dev440 scoring entrypoint hotfix');
assert(core.includes('← All rounds</a> <strong>'));
assert(!core.includes('+ Create Another Event / Round'));
assert(core.includes("name=\"new_event_name\""),'Automatic event creation form must remain available');

console.log('PASS scoring-entrypoint-hotfix-v440');
