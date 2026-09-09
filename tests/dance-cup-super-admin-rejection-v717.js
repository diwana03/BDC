const assert=require('assert');
const fs=require('fs');
const read=file=>fs.readFileSync(file,'utf8');
const review=read('admin/dance-cup/approval-review.php');
const queue=read('admin/dance-cup/approvals.php');
const workspace=read('app/Views/admin/dance-cup-automatic-workspace.php');
const live=read('public/js/dance-cup-scoring-live.js');
const service=read('app/Services/DanceCupScoringService.php');
const version=JSON.parse(read('VERSION.json'));

assert(review.includes('Auth::requireSuperAdmin()'),'result decisions must remain Super Admin-only');
assert(review.includes("in_array($decision,['approve','reject'],true)"),'review must accept only explicit approval or rejection');
assert(review.includes('name="decision" value="reject"'),'review screen is missing its Reject button');
assert(review.includes('name="decision" value="approve"'),'review screen is missing its Approve button');
assert(review.includes('name="data_mode" value="<?=$test?\'test\':\'real\'?>"'),'decision POST must preserve Test/Live mode');
assert(review.includes('DanceCupScoringService::rejectResults'),'Reject must use the protected service');
assert(queue.includes('Review, Approve or Reject')&&workspace.includes('Review, Approve or Reject'),'every Super Admin entry point must advertise both decisions');
assert(live.includes('Open Review, Approve or Reject.'),'live Automatic status refresh must retain the Reject route');

for(const marker of [
  'public static function rejectResults',
  "if((string)$row['status']!=='pending_approval')",
  "SET status='draft',submitted_by=NULL,submitted_at=NULL,approved_by=NULL,approved_at=NULL",
  "status=CASE WHEN status='submitted' THEN 'scoring' ELSE status END,submitted_at=NULL",
  "SET status='cancelled',token_hash=NULL,cancelled_at=NOW()",
  'DELETE FROM {$prefix}_scoring_results',
  "'dance_cup_result_rejected'",
  "'marks_preserved'=>true",
  "'comments_preserved'=>true",
])assert(service.includes(marker),`protected rejection workflow missing ${marker}`);
assert(!service.includes('DELETE FROM {$prefix}_marks'),'rejection must preserve judge marks');
assert(!service.includes('DELETE FROM {$prefix}_judge_comments'),'rejection must preserve private comments');
assert(Number(version.version.match(/^2\.3\.6-dev(\d+)$/)?.[1]||0)>=718);
assert(version.build>=3424);
console.log('dev717 Super Admin Dance Cup rejection checks passed');
