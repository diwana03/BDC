const fs = require('fs');
const assert = require('assert');

const live = fs.readFileSync('admin/scoring/result.php', 'utf8');
const test = fs.readFileSync('admin/scoring-tests/result.php', 'utf8');
const advancement = fs.readFileSync('app/Services/ScoringReportAdvancementService.php', 'utf8');
const specialPublish = fs.readFileSync('admin/scoring/special-publish.php', 'utf8');
const livePublish = fs.readFileSync('admin/scoring/publish.php', 'utf8');
const testPublish = fs.readFileSync('admin/scoring-tests/publish.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

for (const [name, source, testMode] of [
  ['Live Heats report', live, 'false'],
  ['Testing Heats report', test, 'true'],
]) {
  assert(source.includes('ScoringReportAdvancementService::actualRoster($pdo,$round,' + testMode + ')'), `${name} must use the actual next-round roster`);
  assert(source.includes('ScoringReportAdvancementService::annotate($entry,$actualRoster)'), `${name} must annotate every Heats entry`);
  assert(source.includes("(string)$entry['actual_advance_label']"), `${name} must display the actual destination label`);
  assert(source.includes("' · PROMOTED'"), `${name} must identify approved manual promotions`);
  assert(source.includes("return 'NOT ADVANCED'"), `${name} must identify callbacks absent from the actual roster`);
  assert(source.includes('>Advancement</th>'), `${name} must label the roster outcome clearly`);
  assert(source.includes('ScoringReportLabelService::councilDivision($round)'), `${name} must use the public BDC or SDC division label`);
  assert(source.includes('Landscape, All Judges'), `${name} must preserve the complete landscape report`);
  assert(source.includes('Judge Key') && source.includes('Scoring Witnesses'), `${name} must preserve report evidence`);
  assert(source.includes('width:28mm') && source.includes('min-width:28mm'), `${name} must keep advancement text readable`);
}

assert(advancement.includes('parent_round_id=:parent_round OR source_round_id=:source_round'), 'actual roster lookup must follow either child-round relationship');
assert(advancement.includes("entry_status='active'"), 'withdrawn next-round entries must not be reported as advanced');
assert(advancement.includes("'parent_round' => $roundId") && advancement.includes("'source_round' => $roundId"), 'native PDO placeholders must be distinct');
assert(advancement.includes("WHEN 'final' THEN 1 WHEN 'semifinal' THEN 2"), 'Final must be preferred over Semifinal when both exist');
assert(advancement.includes("$roundType === 'final' ? 'FINALIST' : 'SEMIFINALIST'"), 'actual roster service must distinguish the destination round');

assert(specialPublish.includes("const heatsSource='result.php?round_id=<?=$heatsId?>'"), 'special publisher must capture the complete Heats report');
assert(specialPublish.includes("fetchReportPreview(heatsSource+'&layout=fit')"), 'special publisher must capture the all-judge Heats layout');
assert(specialPublish.includes("uploadSpecialReport(makeSpecialReportArchive(heatsViews[0],heatsViews[1],heatsSource,'Heats'),'heats')"), 'special publisher must upload the full Heats snapshot');
assert(specialPublish.includes("document_category IN('heats','finals')"), 'refresh must protect and update both report archives');
assert(specialPublish.includes("'actual_final_roster'=>true"), 'archive refresh must audit its advancement source');
assert(!specialPublish.includes("$heatsBody='<table>"), 'special publisher must not rebuild the old reduced Heats table');

for (const [name, source] of [['Live standard publisher', livePublish], ['Testing standard publisher', testPublish]]) {
  assert(source.includes('result.php?round_id=<?=$heatsId?>'), `${name} must continue snapshotting the detailed Heats endpoint`);
}

assert(version.version === '2.3.6-dev744' && version.build === 3450, 'release metadata mismatch');

console.log('Detailed Heats and actual Final roster v744 checks passed');
