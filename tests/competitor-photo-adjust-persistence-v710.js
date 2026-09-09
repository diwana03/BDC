'use strict';

const fs = require('fs');
const assert = require('assert');

const source = fs.readFileSync('admin/competitors/photo-adjust.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

assert(
  source.includes("$source=$c['photo_url']?:$c['original_photo_url']?:url('public/assets/img/default-competitor.svg');"),
  'The editor must reopen the current adjusted photo before falling back to the original'
);
assert(
  source.includes("original_photo_url=COALESCE(original_photo_url,:source),photo_url=:photo"),
  'Saving an adjustment must preserve the original while updating the current photo'
);
assert(
  source.includes("$original=trim((string)($c['original_photo_url']??''));"),
  'The preserved original must remain available to restore'
);
assert.strictEqual(version.version, '2.3.6-dev719');
assert.strictEqual(version.build, 3425);

console.log('Competitor photo adjustment persistence checks passed.');
