const fs=require('fs'),path=require('path'),root=path.resolve(__dirname,'..');
const read=p=>fs.readFileSync(path.join(root,p),'utf8'),assert=(v,m)=>{if(!v)throw new Error(m)};
const competitors=read('admin/competitors/index.php'),review=read('admin/integration-review/index.php'),version=JSON.parse(read('VERSION.json'));
for(const token of ["'filter'=>$isAll?null:$key","'q'=>null","'division'=>null","'status'=>null"])assert(competitors.includes(token),'summary filter reset missing '+token);
assert(!competitors.includes("$filter === $key ? '' : $key"),'summary card must not toggle its active filter off');
for(const token of ['pendingApiChanges','bdc_api_change_proposals',"status='pending'",'WDC removals appear in API Changes','Review removals and changes'])assert(review.includes(token),'API change visibility missing '+token);
assert(Number((version.version.match(/dev(\d+)$/)||[])[1])>=567&&version.build>=3273,'version mismatch');
console.log('Competitor filters and API change visibility v567 checks passed.');
