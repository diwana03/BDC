const fs=require('fs');
const read=file=>fs.readFileSync(file,'utf8');
const service=read('app/Services/SpecialCategoryRecoveryService.php');
const migration=read('database/migrations/20260825_0300_restore_manual_special_categories.php');
const edit=read('admin/competitors/edit.php');
const list=read('admin/competitors/index.php');
const report=read('admin/competitors/special-category-recovery.php');
const version=JSON.parse(read('VERSION.json'));
for(const category of ['bachata_rising','bachata_open','bachata_invitational','salsa_rising','salsa_open']){
  if(!service.includes(category))throw new Error('Recovery schema missing '+category);
  if(!edit.includes(category))throw new Error('Competitor editor missing '+category);
}
for(const token of ["action IN ('competitor_created','competitor_updated')",'details_json',"$details['division']",'$latest[(int)$row[\'entity_id\']',"bdc_special_category_recovery",'ON DUPLICATE KEY UPDATE'])if(!service.includes(token))throw new Error('Recovery evidence/idempotency missing: '+token);
if(service.includes('bdc_participant_results')||service.includes('bdc_point_transactions')||service.includes('bdc_scoring_entries'))throw new Error('Manual recovery must not infer from competitions');
if(!migration.includes('SpecialCategoryRecoveryService::recoverManualAssignments($pdo,true)'))throw new Error('Migration does not apply audited recovery');
if(!list.includes('special-category-recovery.php')||!report.includes('RECOVERY AUDIT'))throw new Error('Super Admin recovery report is not connected');
if(version.version!=='2.3.3-dev399'||version.build!==3105)throw new Error('VERSION.json is not dev399 build 3105');
console.log('PASS audited manual Special Category recovery v399');
