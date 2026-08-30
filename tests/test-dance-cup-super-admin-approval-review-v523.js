const assert = require('assert');
const fs = require('fs');

const review = fs.readFileSync('admin/dance-cup/approval-review.php', 'utf8');
const queue = fs.readFileSync('admin/dance-cup/approvals.php', 'utf8');
const workspace = fs.readFileSync('app/Views/admin/dance-cup-automatic-workspace.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

assert(review.includes('Auth::requireSuperAdmin()'), 'approval review must be Super Admin-only');
for (const source of ['{$prefix}_scoring_results', '{$prefix}_marks', '{$prefix}_judge_comments']) {
  assert(review.includes(source), `review screen must load ${source}`);
}
for (const label of ['Calculated Official Ranking', 'Judge Scores &amp; Private Comments', 'Private comment', 'Subtotal']) {
  assert(review.includes(label), `review screen missing ${label}`);
}
assert(review.includes("empty($_POST['review_confirmed'])"), 'approval must reject an unchecked review confirmation');
assert(review.includes('I reviewed the ranking, every judge score and all private comments.'), 'confirmation must state exactly what was reviewed');
assert(review.includes('DanceCupScoringService::approveResults'), 'confirmed review must use the protected publication service');
assert(review.includes('Projection reveal remains locked and separately controlled.'), 'approval must not imply an automatic reveal');
assert(queue.includes('approval-review.php?id='), 'approval queue must route into the dedicated review screen');
assert(!queue.includes('<form method="post"'), 'approval queue must not publish inline');
assert(review.includes("DanceCupScoringService::tables($test)"), 'approval review must select the isolated Test or Live tables');
assert(queue.includes("DanceCupScoringService::tables($test)"), 'approval queue must select the isolated Test or Live tables');
assert(review.includes("JOIN {$tables['events']} e"), 'approval review must join the selected Dance Cup event table');
assert(queue.includes("JOIN {$tables['events']} e"), 'approval queue must join the selected Dance Cup event table');
assert(!review.includes('JOIN bdc_events e'), 'approval review must not join the unrelated general event table');
assert(workspace.includes('Review Result, Comments &amp; Accept'), 'automatic workspace must open review rather than approve immediately');
assert(workspace.includes('data-approval-href="approval-review.php?id=<?=$id?><?=$suffix?>"'), 'workspace review action must preserve Test or Live mode');
assert.strictEqual(version.version, '2.3.3-dev536');
assert.strictEqual(version.build, 3242);

console.log('dev523 Super Admin Dance Cup approval review checks passed');
