const assert = require('assert');
const fs = require('fs');

const workspace = fs.readFileSync('app/Views/admin/dance-cup-automatic-workspace.php', 'utf8');
const live = fs.readFileSync('public/js/dance-cup-scoring-live.js', 'utf8');
const projector = fs.readFileSync('admin/dance-cup/projector.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

for (const marker of ['data-dc-approval-link', 'data-approval-href=', 'data-dc-approval-notice', 'Review Result, Comments &amp; Accept']) {
  assert(workspace.includes(marker), `Missing approval workspace marker: ${marker}`);
}
for (const marker of ["state.competition_status==='pending_approval'", "approvalLink.href=pending?approvalLink.dataset.approvalHref:'#';", 'Pending Super Admin approval.']) {
  assert(live.includes(marker), `Missing live approval-state marker: ${marker}`);
}
assert(projector.includes('.podium-card.third .score{font-size:clamp(15px,1.25vw,23px)}'), 'Third-place score must fit inside the stepped card');
assert.strictEqual(version.version, '2.3.3-dev533');
assert.strictEqual(version.build, 3239);

console.log('OK: pending approval appears immediately and stepped podium details remain visible');
