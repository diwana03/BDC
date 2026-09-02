const fs=require('fs');
const service=fs.readFileSync('app/Services/ProfileIntegrationService.php','utf8');
const assert=(condition,message)=>{if(!condition)throw new Error(message)};
for(const token of [
  "in_array('bachata',$p['styles'],true)",
  "allocateBdcIdentity($pdo,$id)",
  "GET_LOCK('bdc-bdc-identity-sequence',10)",
  'bdc_bdc_identity_detachment_archive',
  "INSERT INTO bdc_result_identities(competitor_id,council,identity_code)",
  "RELEASE_LOCK('bdc-bdc-identity-sequence')"
])assert(service.includes(token),'missing safe SDC-person to BDC promotion behavior: '+token);
assert(service.indexOf("allocateBdcIdentity($pdo,$id)")<service.indexOf("foreach($p['styles'] as $dance)"),'BDC identity must exist before Bachata category assignment');
console.log('profile integration BDC promotion v565 checks passed');
