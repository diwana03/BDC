const fs=require('fs'),assert=require('assert');
const projector=fs.readFileSync('admin/dance-cup/projector.php','utf8');
const launch=fs.readFileSync('admin/dance-cup/projector-launch.php','utf8');
for(const marker of ['card contestant-card','class="contestant-name"','.contestant-card .contestant-name{','font-size:clamp(22px,1.7vw,34px)','.contestant-card .identity-meta{','font-size:clamp(20px,1.55vw,31px)','.contestant-card .identity-meta .flag{font-size:1.75em'])assert(projector.includes(marker),'missing enlarged contestant identity marker: '+marker);
assert(projector.includes('pageSize=groupEntry?8:10'),'solo enlargement must retain ten cards while group entries receive eight wider cards');
assert(launch.includes("'&presentation=714'"),'projector launch must invalidate the previous presentation document');
console.log('dev508 Dance Cup contestant name and flag scale checks passed');
