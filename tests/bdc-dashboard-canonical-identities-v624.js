const fs=require('fs'),path=require('path'),root=path.resolve(__dirname,'..');
const read=p=>fs.readFileSync(path.join(root,p),'utf8'),assert=(v,m)=>{if(!v)throw new Error(m)};
const dashboard=read('admin/competitors/index.php');
const version=JSON.parse(read('VERSION.json'));

assert(
  dashboard.includes('SELECT id competitor_id,bdc_id identity_code FROM bdc_competitors WHERE bdc_id LIKE'),
  'Bachata dashboard list, search, pagination and CSV must use canonical bdc_competitors.bdc_id identities'
);
assert(
  !dashboard.includes("FROM bdc_result_identities WHERE council='bdc'"),
  'Bachata dashboard must not exclude competitors missing a legacy result-identity mirror'
);
for(const scope of ["'all_participants'","'missing_photo'","'missing_country'","'incomplete_profile'","'special_category'"]){
  assert(dashboard.includes(scope),scope+' summary scope missing');
}
assert(
  dashboard.includes("c.status='active' AND c.bdc_id LIKE 'BDC-%'"),
  'Bachata summary counts must use active canonical BDC profiles'
);
assert(version.version==='2.3.3-dev624'&&version.build===3330,'version mismatch');
console.log('dev624 canonical Bachata dashboard identity checks passed');
