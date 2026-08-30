const fs=require('fs'),assert=require('assert');
const projector=fs.readFileSync('admin/dance-cup/projector.php','utf8');
const feed=fs.readFileSync('admin/dance-cup/projection-feed.php','utf8');
const bootstrap=fs.readFileSync('bootstrap.php','utf8');
const version=JSON.parse(fs.readFileSync('VERSION.json','utf8'));

assert(projector.includes("let lastHash='',lastRevision='',busy=false,pollTimer=null"),'projector must track a server revision and serialize requests');
assert(projector.includes('function schedulePoll(delay=1000)'),'normal visible refresh must run every second');
assert(projector.includes("since=lastRevision?'&since='"),'projector must send its last feed revision');
assert(projector.includes('if(data.unchanged)'),'projector must handle lightweight unchanged responses');
assert(!projector.includes('setInterval(poll,5000)'),'legacy five-second fixed polling must be removed');
assert(feed.includes("$revision=hash('sha256'"),'feed must build a stable revision from projection and scoring data');
assert(feed.includes("hash_equals($revision,$clientRevision)"),'feed must safely compare the client revision');
assert(feed.includes("'unchanged'=>true"),'feed must skip the full payload when nothing changed');
assert(feed.includes("'revision'=>$revision,'state'=>$state"),'changed responses must carry the next revision');
assert(bootstrap.includes("/admin/dance-cup/projector(?:-launch)?\\.php$#i"),'projector must be excluded from universal admin navigation injection');
assert.strictEqual(version.version,'2.3.3-dev533');
assert.strictEqual(version.build,3239);
console.log('Dance Cup projector fast refresh v532 checks passed');
