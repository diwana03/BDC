const fs = require('fs');
const assert = require('assert');

const read = path => fs.readFileSync(path, 'utf8');
const migration = read('database/migrations/20260828_0100_dance_cup_category_edit_unlock.php');
const service = read('app/Services/DanceCupCategoryEditService.php');
const editor = read('admin/dance-cup/edit-category.php');
const alias = read('admin/dance-cup/category-edit.php');
const index = read('admin/dance-cup/index.php');
const judge = read('admin/dance-cup/judge-scoring.php');
const manual = read('admin/dance-cup/category.php');
const api = read('admin/dance-cup/scoring-api.php');
const panel = read('app/Services/DanceCupJudgingPanelService.php');
const version = JSON.parse(read('VERSION.json'));

assert(migration.includes("'bdc_dance_cup_competitions','bdc_test_dance_cup_competitions'"));
for (const column of ['edit_unlocked_at', 'edit_unlocked_by', 'edit_unlock_reason']) {
  assert(migration.includes(column));
  assert(service.includes(column));
}
assert(service.includes("'UNLOCK'"));
assert(service.includes("'RESET SCORES'"));
assert(service.includes("'pending_approval','approved'"));
assert(service.includes("'submitted','pending_approval','approved'"));
assert(service.includes("DELETE FROM {$p}_marks"));
assert(service.includes("DELETE FROM {$p}_scoring_results"));
assert(service.includes("dance_cup_category_edit_unlocked"));
assert(service.includes("dance_cup_category_edited"));
assert(editor.includes('Scoring has started') || editor.includes('SCORING STARTED'));
assert(editor.includes('Metadata-only changes preserve all existing marks'));
assert(alias.includes("require __DIR__.'/edit-category.php'"));
assert(index.includes('category-edit.php?id='));
assert(judge.includes('DanceCupCategoryEditService::ensureColumns'));
assert(judge.includes('Category editing in progress'));
assert(judge.includes('http_response_code(423)'));
assert(manual.includes('DanceCupCategoryEditService::ensureColumns'));
assert(manual.includes('http_response_code(423)'));
assert(api.includes('DanceCupCategoryEditService::ensureColumns'));
assert(api.includes('edit_unlocked_at'));
assert(panel.includes('c.edit_unlocked_at'));
assert(!service.toLowerCase().includes('jack and jill'));
assert(version.build >= 3166, 'dev460 protected category editing must remain in later builds');
console.log('Dance Cup protected category editing dev460 checks passed.');
