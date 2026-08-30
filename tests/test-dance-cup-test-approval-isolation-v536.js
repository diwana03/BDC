const assert = require('assert');
const fs = require('fs');

const read = file => fs.readFileSync(file, 'utf8');
const review = read('admin/dance-cup/approval-review.php');
const queue = read('admin/dance-cup/approvals.php');
const category = read('admin/dance-cup/category.php');
const workspace = read('app/Views/admin/dance-cup-automatic-workspace.php');
const service = read('app/Services/DanceCupScoringService.php');
const live = read('public/js/dance-cup-scoring-live.js');
const version = JSON.parse(read('VERSION.json'));

for (const source of [review, queue]) {
  assert(source.includes("$test=(string)($_GET['data_mode']"), 'approval route must resolve data_mode before reading records');
  assert(source.includes("DanceCupScoringService::tables($test)"), 'approval route must use the selected Test or Live table map');
  assert(source.includes("$prefix=$test?'bdc_test_dance_cup':'bdc_dance_cup'"), 'approval route must select the isolated prefix');
}

for (const forbidden of [
  'FROM bdc_dance_cup_competitions',
  'FROM bdc_dance_cup_criteria',
  'FROM bdc_dance_cup_entries',
  'FROM bdc_dance_cup_judges',
  'FROM bdc_dance_cup_marks',
  'FROM bdc_dance_cup_judge_comments',
  'FROM bdc_dance_cup_scoring_results',
]) {
  assert(!review.includes(forbidden), `approval review contains a Live-only read: ${forbidden}`);
}

assert(category.includes('judge-sheet.php?id=<?=$id?><?=$suffix?>'), 'Manual detailed results must preserve Test mode');
assert(workspace.includes('judge-sheet.php?id=<?=$id?><?=$suffix?>'), 'Automatic detailed results must preserve Test mode');
assert(workspace.includes('data-approval-href="approval-review.php?id=<?=$id?><?=$suffix?>"'), 'automatic approval link must preserve Test mode after AJAX submit');
assert(!workspace.includes('if(!$test&&\\App\\Core\\Auth::isSuperAdmin())'), 'Test approval review must not be hidden from Super Admin');
assert(review.includes('judge-sheet.php?id=<?=$id?><?=$suffix?>'), 'approval print link must preserve Test mode');
assert(review.includes('name="data_mode" value="<?=$test?\'test\':\'real\'?>"'), 'approval POST must preserve Test mode');
assert(queue.includes('approval-review.php?id=<?=(int)$row[\'id\']?><?=$suffix?>'), 'approval queue review link must preserve Test mode');
assert(queue.includes("<?=$row['scoring_mode']==='automatic'?'automatic-setup.php':'category.php'?>?id="), 'approval queue must retain workflow-specific workspace routing');

assert(service.includes("$tables=self::tables($test);$prefix=$test?'bdc_test_dance_cup':'bdc_dance_cup'"), 'approval service must validate against the selected data mode');
assert(service.includes('if(!$test){'), 'permanent result history writes must be guarded from Test mode');
assert(!service.includes("if($test)throw new RuntimeException('Test Dance Cup results cannot be published to permanent history.')"), 'Test mode must support an isolated approval simulation');
assert(review.includes('Permanent history was not changed.'), 'Test approval must explicitly confirm that Live history is unchanged');
assert(live.includes("approvalNotice.dataset.dcTestMode==='1'"), 'live approval notice must distinguish Test approval from permanent publication');

assert.strictEqual(version.version, '2.3.3-dev537');
assert.strictEqual(version.build, 3243);

console.log('dev536 Dance Cup Test approval isolation checks passed');
