const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const control = fs.readFileSync(path.join(root, 'admin/live-screen/control.php'), 'utf8');

assert.match(control, /\$flightButtons\[\$flightNumber\] = 'Flight Round ' \. \$flightNumber/);
assert.match(control, /data-screen="flights" data-page="<\?=\(int\)\$flightNumber\?>"/);
assert.match(control, /Number\(b\.dataset\.page\)===Number\(j\.session\.page_number\|\|1\)/);
assert.match(control, /extra\.page_number=b\.dataset\.page/);
assert.doesNotMatch(control, /\['flights' => 'Flight Call'\] \+ \$types/);

console.log('Separate projector flight-round controls regression checks passed.');
