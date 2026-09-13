const fs = require('fs');
const assert = require('assert');

const special = fs.readFileSync('admin/scoring/special-publish-salsa.php', 'utf8');
const standard = fs.readFileSync('admin/scoring/publish-salsa.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

for (const [label, source] of [['special Salsa publication', special], ['standard Salsa publication', standard]]) {
  assert(source.includes('published_by=:published_by,approved_by=:approved_by'), `${label} must use distinct native PDO placeholders`);
  assert(source.includes("'published_by'=>$userId?:null,'approved_by'=>$userId?:null"), `${label} must bind both audit columns explicitly`);
  assert(!source.includes('published_by=:u,approved_by=:u'), `${label} must not reuse a named placeholder under native PDO prepares`);
}

assert(special.includes('$pdo->beginTransaction();try{'), 'special approval must remain transactional');
assert(special.includes('if($pdo->inTransaction())$pdo->rollBack()'), 'special approval must preserve rollback safety');
assert(version.version === '2.3.6-dev741' && version.build === 3447, 'release metadata mismatch');

console.log('Salsa publication approval binding v741 checks passed');
