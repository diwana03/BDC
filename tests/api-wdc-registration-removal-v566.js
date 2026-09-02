const fs=require('fs'),path=require('path'),root=path.resolve(__dirname,'..');
const read=p=>fs.readFileSync(path.join(root,p),'utf8');
const service=read('app/Services/ApiChangeProposalService.php'),version=JSON.parse(read('VERSION.json'));
const assert=(v,m)=>{if(!v)throw new Error(m)};
for(const token of ["'wdc.remove_registration'",'WDC removal requires valid event_key and category_key values.','wdcRegistrationSnapshot','Official Dance Cup history protects this WDC registration.',"SET status='withdrawn'","SET status='archived'",'active_registration_count'])assert(service.includes(token),'missing '+token);
assert(service.includes("count($rows)!==1"),'removal must fail closed unless exactly one active registration exists');
assert(service.includes("bdc_dance_cup_result_history")&&service.includes('bdc_wdc_championship_points'),'official WDC history checks missing');
assert(/^2\.3\.3-dev\d+$/.test(version.version)&&version.build>=3272,'version minimum mismatch');
console.log('WDC registration removal v566 checks passed.');
