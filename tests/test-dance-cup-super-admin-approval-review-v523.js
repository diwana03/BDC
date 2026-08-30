const assert = require('assert');
const fs = require('fs');

const review = fs.readFileSync('admin/dance-cup/approval-review.php', 'utf8');
const queue = fs.readFileSync('admin/dance-cup/approvals.php', 'utf8');
const workspace = fs.readFileSync('app/Views/admin/dance-cup-automatic-workspace.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

assert(review.includes('Auth::requireSuperAdmin()'), 'approval review must be Super Admin-only');
for (const source of ['bdc_dance_cup_scoring_results', 'bdc_dance_cup_marks', 'bdc_dance_cup_judge_comments']) {
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
assert(review.includes('JOIN bdc_dance_cup_events e'), 'approval review must join the dedicated Dance Cup event table');
assert(queue.includes('JOIN bdc_dance_cup_events e'), 'approval queue must join the dedicated Dance Cup event table');
assert(!review.includes('JOIN bdc_events e'), 'approval review must not join the unrelated general event table');
assert(workspace.includes('Review Result, Comments &amp; Accept'), 'automatic workspace must open review rather than approve immediately');
assert(workspace.includes('data-approval-href="approval-review.php?id=<?=$id?>"'), 'workspace review action must target the selected competition');
assert.strictEqual(version.version, '2.3.3-dev529');
assert.strictEqual(version.build, 3235);

console.log('dev523 Super Admin Dance Cup approval review checks passed');
