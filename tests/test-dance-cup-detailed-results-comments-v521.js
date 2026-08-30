const assert = require('assert');
const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');

const report = read('admin/dance-cup/judge-sheet.php');
const approvals = read('admin/dance-cup/approvals.php');
const workspace = read('app/Views/admin/dance-cup-automatic-workspace.php');
const category = read('admin/dance-cup/category.php');
const version = JSON.parse(read('VERSION.json'));

assert(report.includes('$canViewPrivateComments=Auth::isSuperAdmin()'), 'private comments must be gated by Super Admin authorization');
assert(report.includes('if($canViewPrivateComments){'), 'ordinary admins must not query private comments');
assert(report.includes('FROM {$prefix}_judge_comments'), 'Super Admin report must load saved judge comments');
assert(report.includes('SUPER ADMIN ONLY'), 'comment pages must be visibly confidential');
assert(report.includes('excluded from public results, projection and ordinary admin reports'), 'private comment exclusion must be explicit');
assert(report.includes('Detailed Judge Result'), 'report must use result terminology');
for (const marker of ['Final Result', 'All contestants and judges', 'summary-table', 'Combined Score', 'Individual judge criterion pages follow.']) {
  assert(report.includes(marker), `missing consolidated result marker: ${marker}`);
}
assert(!report.includes('Consolidated Official Result') && !report.includes('Competition Results') && !report.includes('result overview'), 'report must use the exact generic Final Result terminology');
assert(report.includes('$rankedEntries=$entries') && report.includes('usort($rankedEntries'), 'consolidated result must build a placement-sorted contestant list');
assert(report.includes('foreach($rankedEntries as $entry)'), 'consolidated result must render official ranking order');

assert(approvals.includes('private_comments'), 'approval list must expose a private comment count to Super Admin');
assert(approvals.includes('Review Result, Comments &amp; Accept'), 'approval queue must link to dedicated confidential review');
assert(approvals.includes('approval-review.php'), 'approval must continue on the dedicated Super Admin screen');
assert(approvals.includes('Projection reveal is controlled separately'), 'publication must be distinguished from projection reveal');

for (const ui of [workspace, category]) {
  assert(ui.includes('Detailed Judge Results'), 'scoring UI must link to the detailed result report');
  assert(!ui.includes('Print Judge Sheets'), 'obsolete Judge Sheets wording must be removed');
}
assert(workspace.includes('Calculate &amp; Preview Result'), 'calculate must be described as a preview');
assert(workspace.includes('Submit Results for Approval &amp; Lock'), 'submit must describe approval and locking');
assert(workspace.includes('it does not publish or reveal the result'), 'submit consequences must be explicit');
assert.strictEqual(version.version, '2.3.3-dev537');
assert.strictEqual(version.build, 3243);

console.log('dev521 Dance Cup detailed result and confidential comment checks passed');
