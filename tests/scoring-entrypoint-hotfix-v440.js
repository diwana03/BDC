const fs=require('fs');
const assert=require('assert');

const core=fs.readFileSync('admin/scoring/core.php','utf8');
const version=JSON.parse(fs.readFileSync('VERSION.json','utf8'));

assert.equal(version.version,'2.3.3-dev440');
assert(core.includes('← All rounds</a> <strong>'));
assert(!core.includes('+ Create Another Event / Round'));
assert(core.includes("name=\"new_event_name\""),'Automatic event creation form must remain available');

console.log('PASS scoring-entrypoint-hotfix-v440');
