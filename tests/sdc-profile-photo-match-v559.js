const fs=require('fs');const read=p=>fs.readFileSync(p,'utf8');const service=read('app/Services/ProfileIntegrationService.php'),sdc=read('app/Services/SdcCompetitorService.php'),migration=read('database/migrations/20260902_0500_repair_sdc_photo_matches.php');const assert=(v,m)=>{if(!v)throw new Error(m)};
for(const token of ["'sdc_id'=>", 'SdcCompetitorService::bySdcId'])assert(service.includes(token),'SDC match missing '+token);
for(const token of ['WHERE s.sdc_id=:id',"s.status='active'"])assert(sdc.includes(token),'canonical SDC match missing '+token);
for(const token of ['sdc-missing-photos-20260902-01','sdc-photo-audit','SDC-000092','SDC-000093',"match_status='matched'","status='pending'"])assert(migration.includes(token),'repair missing '+token);
console.log('SDC profile photo match v559 checks passed.');
