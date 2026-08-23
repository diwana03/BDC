const fs=require('fs');

const root=__dirname+'/..';
const test=fs.readFileSync(root+'/admin/scoring-tests/index.php','utf8');
const live=fs.readFileSync(root+'/admin/scoring/core.php','utf8');
const markers=[
  "calculated.sort((a,b)=>b.total-a.total||a.entry-b.entry)",
  "crossesCallback||crossesAlternate||tiedAlternate",
  "status='Tie Pending';rowClass='tie_pending'",
  "classList.remove('callback','alternate','tie_pending')"
];
for(const marker of markers){
  if(!test.includes(marker))throw new Error('Test preview missing '+marker);
  if(!live.includes(marker))throw new Error('Live preview missing '+marker);
}
if(test.includes('b.chief-a.chief||b.yes-a.yes')||live.includes('b.chief-a.chief||b.yes-a.yes'))throw new Error('Forbidden Chief/YES tie-break remains in Live Preview.');

function classify(totals,callbackCount){
  const rows=totals.map((total,entry)=>({total,entry})).sort((a,b)=>b.total-a.total||a.entry-b.entry);
  const alternateLimit=Math.min(callbackCount+3,rows.length),out=[];
  for(let start=0;start<rows.length;){
    let end=start;while(end+1<rows.length&&rows[end+1].total===rows[start].total)end++;
    const a=start+1,b=end+1,crossesCallback=a<=callbackCount&&b>callbackCount,crossesAlternate=a<=alternateLimit&&b>alternateLimit,tiedAlternate=end>start&&a>callbackCount&&b<=alternateLimit;
    const status=crossesCallback||crossesAlternate||tiedAlternate?'Tie Pending':(b<=callbackCount?'Callback':(a>callbackCount&&b<=alternateLimit?'Alternate':'Eliminated'));
    for(let i=start;i<=end;i++)out.push({total:rows[i].total,rank:a,status});start=end+1;
  }
  return out;
}

const leader=classify([30,30,30,20,20,20],5);
if(leader.filter(x=>x.total===20).some(x=>x.status!=='Tie Pending'||x.rank!==4))throw new Error('Three-way callback-boundary tie was split.');
const follower=classify([30,30,30,30,20,10],5);
if(follower[4].status!=='Callback'||follower[5].status!=='Alternate')throw new Error('Non-tied follower boundary changed.');
console.log('OK: manual Test/Live preview preserves callback-boundary ties');
