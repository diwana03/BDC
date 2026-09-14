const fs = require('fs');
const assert = require('assert');

const live = fs.readFileSync('admin/scoring/final-result.php', 'utf8');
const test = fs.readFileSync('admin/scoring-tests/final-result.php', 'utf8');
const labels = fs.readFileSync('app/Services/ScoringReportLabelService.php', 'utf8');
const specialPublish = fs.readFileSync('admin/scoring/special-publish.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

for (const [name, source] of [['Testing Final report', test], ['Live Final report', live]]) {
  assert(source.includes('ScoringReportLabelService::councilDivision($round)'), `${name} must use the public council level`);
  assert(source.includes("- <?=e($publicDivision)?> - Final -"), `${name} must follow the event - council level title format`);
  assert(source.includes('FINAL · JUDGE RANKINGS'), `${name} must include every judge ranking`);
  assert(source.includes('FINAL · RELATIVE PLACEMENT'), `${name} must include the Relative Placement evidence`);
  assert(source.includes("$pair['leader_bib']") && source.includes("$pair['follower_bib']"), `${name} must retain both finalist bibs`);
  assert(source.includes('Chief Judge') && source.includes('Judge Key'), `${name} must identify the Chief Judge and complete panel`);
  assert(source.includes('Witness') && source.includes('$witnesses'), `${name} must retain witness details`);
  assert(source.includes('Print / Save as PDF'), `${name} must retain print and PDF output`);
  assert(source.includes('Landscape, All Judges'), `${name} must retain the full-width landscape report`);
}

assert(labels.includes("'rising'=>'INTERMEDIATE'"), 'Rising must publish using the established Intermediate public level');
assert(labels.includes("return ($dance==='salsa'?'SDC':'BDC').' '.$level"), 'report council must follow Salsa SDC and Bachata BDC identity');
assert(specialPublish.includes("fetchFinalPreview(source+'&layout=fit')"), 'special publication must archive the detailed landscape report');
assert(specialPublish.includes('makeSpecialFinalArchive(readable,landscape,source)'), 'special publication must archive both official report layouts');
assert(version.version === '2.3.6-dev743' && version.build === 3449, 'release metadata mismatch');

for (const marker of [
  'ScoringReportLabelService::councilDivision($round)',
  '<?=e($publicDivision)?> · FINAL · JUDGE RANKINGS',
  '<?=e($publicDivision)?> · FINAL · RELATIVE PLACEMENT',
  '<strong>Division</strong><br><?=e($publicDivision)?>',
]) {
  assert.strictEqual((live.match(new RegExp(marker.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g')) || []).length, (test.match(new RegExp(marker.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g')) || []).length, `Testing and Live must use ${marker} equally`);
}

console.log('Council-standard detailed Final report v743 checks passed');
