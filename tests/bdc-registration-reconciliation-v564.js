const fs=require('fs');
const assert=require('assert');
const read=path=>fs.readFileSync(path,'utf8');
const service=read('app/Services/ApiChangeProposalService.php');
const diagnostics=read('app/Services/ProfileDiagnosticQueryService.php');
const docs=read('docs/profile-integration-api-v1.md');

const detachStart=service.indexOf("if($type==='bdc.detach_identity'){");
const updateStart=service.indexOf("if($type==='bdc.update'){",detachStart);
const detach=service.slice(detachStart,updateStart);
const updateEnd=service.indexOf("if($type==='competitor.archive')",updateStart);
const update=service.slice(updateStart,updateEnd);

assert(detachStart>=0&&updateStart>detachStart&&updateEnd>updateStart,'active BDC action branches are missing');
assert(service.includes("private const BDC_CATEGORIES=['bachata_rising','bachata_open','bachata_invitational']"),'exact Bachata special-category allowlist is missing');
assert(service.includes("$division!==''&&$division!=='unknown'"),'API must reject fabricated earned progression');
assert(service.includes('BDC reconciliation requires at least one valid Bachata special category.'),'BDC reconciliation must require sheet-backed categories');

for(const marker of ["bachata_result_count']>0","bachata_point_transaction_count']>0",'active_sdc_profile_count'])assert(detach.includes(marker),'detachment safety is missing '+marker);
assert(detach.indexOf("bachata_result_count']>0")<detach.indexOf('bdc_bdc_identity_detachment_archive'),'history must be checked before archival or mutation');
assert(detach.indexOf('bdc_bdc_identity_detachment_archive')<detach.indexOf('DELETE FROM bdc_result_identities'),'recovery archive must be written before BDC detachment');
for(const marker of ["council='bdc'","dance_style='bachata'","SET bdc_id=NULL"])assert(detach.includes(marker),'detachment is not correctly Bachata-scoped: '+marker);
for(const forbidden of ['DELETE FROM bdc_sdc_competitors','UPDATE bdc_sdc_competitors','DELETE FROM bdc_sdc_competitor_categories','DELETE FROM bdc_wdc_identities','DELETE FROM bdc_competitors'])assert(!detach.includes(forbidden),'detachment may damage preserved shared/SDC/WDC data: '+forbidden);

for(const marker of ["current_division']??'')==='unknown'","bachata_result_count']>0","bachata_point_transaction_count']>0",'bdc_competitor_discipline_profiles','bdc_competitor_special_categories'])assert(update.includes(marker),'BDC update guard/write path is missing '+marker);
assert(update.indexOf("bachata_result_count']>0")<update.indexOf('INSERT INTO bdc_competitor_discipline_profiles'),'history must be protected before profile mutation');
assert(update.includes("dance_style='bachata'")&&update.includes("VALUES(:id,'bachata'"),'BDC updates must be Bachata-scoped');
for(const forbidden of ['bdc_sdc_competitors','bdc_sdc_competitor_categories','bdc_wdc_identities','bdc_participant_results SET','bdc_point_transactions SET'])assert(!update.includes(forbidden),'BDC update crosses a council/scoring boundary: '+forbidden);

assert(diagnostics.includes("'bdc_members'=>self::rows"),'read-only BDC audit query is not routed');
for(const marker of ['bachata_result_count','bachata_point_transaction_count','bachata_points','bachata_categories','LOWER(c.exact_name)'])assert(diagnostics.includes(marker),'BDC audit output is missing '+marker);
for(const forbidden of ['INSERT ','UPDATE ','DELETE ','beginTransaction','commit()'])assert(!diagnostics.includes(forbidden),'diagnostic service must remain read-only: '+forbidden);
assert(docs.includes('verified SDC or Dance Cup person')&&docs.includes('Special-category registration never fabricates Novice progression'),'API safety contract is incomplete');

console.log('BDC registration reconciliation v564 checks passed');
