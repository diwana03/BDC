const fs = require('fs');
const assert = require('assert');

const live = fs.readFileSync('admin/scoring/publish.php', 'utf8');
const test = fs.readFileSync('admin/scoring-tests/publish.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

for (const [name, source, documentTable] of [
  ['Live publisher', live, 'bdc_result_documents'],
  ['Testing publisher', test, 'bdc_test_result_documents'],
]) {
  assert(source.includes("if($action==='refresh_result_archives')"), `${name} must handle detailed archive refresh`);
  assert(source.includes("refreshPublishedArchivedHtml($pdo,$roundId,(int)$publication['id'],$userId,'" + documentTable + "')"), `${name} must refresh its own result documents`);
  assert(source.includes('Refresh Detailed Result Archives'), `${name} must expose the Super Admin refresh control`);
  assert(source.includes('Refresh Heats &amp; Final Reports'), `${name} must state the report-only refresh scope`);
  assert(source.includes("foreach(['heats','finals'] as $category)"), `${name} must validate both replacement reports before changing an archive`);
  assert(source.includes('d.storage_path,d.url'), `${name} must load the published URL for legacy archive recovery`);
  assert(source.includes("parse_url($url,PHP_URL_QUERY)") && source.includes("$urlParameters['file']"), `${name} must recover the exact filename from result-file.php URLs`);
  assert(source.includes('ResultStorageService::resolveFilename'), `${name} must confine legacy archive recovery to the protected result repository`);
  assert(source.includes("basename(str_replace('\\\\','/',$storagePath))"), `${name} must support legacy stored paths by filename only`);
  assert(source.includes("'/.archive-backups'"), `${name} must create protected backups`);
  assert(source.includes("'previous'=>hash_file") && source.includes("'new'=>hash_file"), `${name} must audit previous and new checksums`);
  assert(source.includes("copy($backupPath,(string)$documents[$category]['target'])"), `${name} must restore every previous archive on failure`);
  assert(source.includes("'actual_final_roster'=>true"), `${name} must record the authoritative Heats advancement source`);
  assert(source.includes("['heats','result.php?round_id=<?=$heatsId?>',true]"), `${name} must capture the detailed Heats source`);
  assert(source.includes("['finals','final-result.php?round_id=<?=$roundId?>',true]"), `${name} must capture the detailed Final source`);
  assert(source.includes("fetchPreviewHtml(url+'&layout=fit')"), `${name} must capture the all-judge landscape layouts`);
  assert(source.includes('data-layout="readable"') && source.includes('data-layout="fit"'), `${name} archives must expose both report layouts`);
  assert(source.includes('scores, placements and points were unchanged'), `${name} must explicitly preserve official result data`);
  assert(source.includes('const archiveButton=refreshArchiveButton||finalApproveButton;'), `${name} must bind the published refresh form before the hidden approval modal`);
  assert(source.includes('approvalForm.querySelector(\'input[name="client_html_ready"]\')'), `${name} must use the readiness field belonging to the active form`);
  assert(source.includes('refreshHtmlGenerationStatus') && source.includes('approvalHtmlGenerationStatus'), `${name} must keep refresh and approval progress messages separate`);
  assert(!source.includes('id="clientHtmlReady"') && !source.includes('id="htmlGenerationStatus"'), `${name} must not retain duplicate archive element IDs`);

  const refreshBlock = source.slice(source.indexOf("if($action==='refresh_result_archives')"), source.indexOf("if($action==='rollback')"));
  assert(!refreshBlock.includes('bdc_point_transactions'), `${name} refresh must not touch points`);
  assert(!refreshBlock.includes('bdc_participant_results'), `${name} refresh must not touch participant results`);
  assert(!refreshBlock.includes('bdc_scoring_results'), `${name} refresh must not touch scoring results`);
}

assert(version.build >= 3451, 'standard detailed archive refresh requires build 3451 or newer');

console.log('Standard detailed result archive refresh v745 checks passed');
