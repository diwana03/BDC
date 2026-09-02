const fs = require('fs');
const assert = require('assert');

const page = fs.readFileSync('admin/dance-cup/participants.php', 'utf8');
const edit = fs.readFileSync('admin/dance-cup/competitor-edit.php', 'utf8');
const photo = fs.readFileSync('admin/competitors/photo-adjust.php', 'utf8');

for (const removed of ['Registration Status', 'Approved Reusable Profiles', 'Published Results', 'Winning Results', '<th>BDC ID</th>', 'bdc_profile_request_dance_cup_categories', 'bdc_profile_requests']) {
  assert(!page.includes(removed), `participant workspace still exposes workflow clutter: ${removed}`);
}
for (const required of ['Dance Participation', 'Edit WDC', 'Adjust Photo', 'dc-photo', 'bdc_wdc_identities', 'bdc_wdc_registrations', 'w.identity_code', 'WDC ID', 'Each approved WDC identity and category registration is shown once']) {
  assert(page.includes(required), `missing WDC participant feature: ${required}`);
}
assert(page.includes("w.status='active'") && page.includes("r.status='registered'"), 'only active WDC identities and approved registrations may be shown');
assert(page.includes("ORDER BY LOWER(w.display_name),w.id,LOWER(r.category_name),r.id"), 'WDC rows need deterministic participant and category ordering');
assert(edit.includes('WDC ID'), 'WDC edit page must retain the permanent WDC code');
assert(photo.includes("$return!=='../dance-cup/participants.php'"), 'photo editor must safely accept the Dance Cup return target');

console.log('dev563 WDC Dance Cup participant workspace checks passed');
