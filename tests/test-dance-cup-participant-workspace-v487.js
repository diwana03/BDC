const fs = require('fs');
const assert = require('assert');

const page = fs.readFileSync('admin/dance-cup/participants.php', 'utf8');
const edit = fs.readFileSync('admin/competitors/edit.php', 'utf8');
const photo = fs.readFileSync('admin/competitors/photo-adjust.php', 'utf8');

for (const removed of ['Registration Status', 'Approved Reusable Profiles', 'Published Results', 'Winning Results', '<th>Registration</th>', 'approvals.php']) {
  assert(!page.includes(removed), `participant workspace still exposes workflow clutter: ${removed}`);
}
for (const required of ['Dance Participation', 'Edit Profile', 'Adjust Photo', 'dc-photo', "registration_status IS NULL OR d.registration_status<>'rejected'", '$seen[$key]', 'Duplicate submissions are consolidated']) {
  assert(page.includes(required), `missing simplified participant feature: ${required}`);
}
assert(page.includes("$return='../dance-cup/participants.php'"), 'profile actions must return to Dance Cup participants');
assert(edit.includes("$return!=='../dance-cup/participants.php'") && photo.includes("$return!=='../dance-cup/participants.php'"), 'competitor editors must safely accept the Dance Cup return target');
assert(edit.includes('href="<?=e($return)?>">Back</a>'), 'competitor editor Back action must honor the safe return target');

console.log('dev487 simplified Dance Cup participant workspace checks passed');
