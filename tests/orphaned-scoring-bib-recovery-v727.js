const fs = require('fs');

const assert = (value, message) => {
  if (!value) throw new Error(message);
};
const read = file => fs.readFileSync(file, 'utf8');
const method = (source, signature, nextSignature) => {
  const start = source.indexOf(signature);
  const end = source.indexOf(nextSignature, start + signature.length);
  assert(start >= 0 && end > start, `cannot isolate ${signature}`);
  return source.slice(start, end);
};

const lifecycle = read('app/Services/ScoringEntryLifecycleService.php');
const integration = read('app/Services/EventIntegrationService.php');
const automatic = read('admin/scoring/automatic-setup-action.php');

const restore = method(lifecycle, 'public static function restoreOrInsert(', '\n    }\n}');
assert(lifecycle.includes("'bdc_scoring_entries'") && lifecycle.includes("'bdc_test_scoring_entries'"), 'shared lifecycle must allow both Live and Test tables');
assert(restore.includes("entry_status='active' LIMIT 1 FOR UPDATE"), 'active competitor conflicts must be locked and rejected');
assert(restore.includes("entry_status='withdrawn' ORDER BY id DESC LIMIT 1 FOR UPDATE"), 'withdrawn competitor must be selected for recovery');
assert(restore.includes('dance_role=:role AND bib_number=:bib LIMIT 1 FOR UPDATE'), 'requested bib must be locked before recovery');
assert(restore.includes("SET bib_number=1000000+id,updated_at=NOW()") && restore.includes("entry_status='withdrawn'"), 'only an inactive bib collision may be moved to its private tombstone bib');
assert(restore.includes("entry_status='active',updated_at=NOW()"), 'same withdrawn competitor must be restored instead of duplicated');
assert(restore.includes('INSERT INTO {$entries}') && !restore.includes('DELETE FROM'), 'new identities may insert while recoverable history is never deleted');
assert(restore.includes('$pdo->beginTransaction()') && restore.includes('$pdo->commit()') && restore.includes('$pdo->rollBack()'), 'direct dashboard additions must recover atomically');

const additions = method(integration, 'private static function applyExistingJackJillCompetitors(', 'private static function applyExistingJackJillRosterSync(');
const sync = method(integration, 'private static function applyExistingJackJillRosterSync(', 'private static function applyExistingJackJillRosterChange(');
for (const [name, body] of [['approval additions', additions], ['approval synchronization', sync]]) {
  assert(body.includes('ScoringEntryLifecycleService::restoreOrInsert('), `${name} must use the shared recovery path`);
  assert(body.includes("$entries=$test?'bdc_test_scoring_entries':'bdc_scoring_entries'"), `${name} must preserve Test and Live isolation`);
}
assert(automatic.includes("ScoringEntryLifecycleService::restoreOrInsert($pdo,'bdc_scoring_entries'"), 'Live Automatic dashboard additions must use the same recovery path');

const version = JSON.parse(read('VERSION.json'));
assert(version.version === '2.3.6-dev727' && version.build === 3433, 'release metadata mismatch');

console.log('Orphaned scoring bib recovery v727 checks passed');
