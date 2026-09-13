const fs = require('fs');
const assert = require('assert');

const live = fs.readFileSync('admin/scoring/core.php', 'utf8');
const test = fs.readFileSync('admin/scoring-tests/index.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

function verifyFinalistRemoval(source, pairTable, label) {
  const query = `SELECT id FROM ${pairTable} WHERE round_id=:r AND (leader_entry_id=:leader_entry OR follower_entry_id=:follower_entry)`;
  assert(source.includes(query), `${label} Final removal must use one binding per native SQL placeholder`);
  assert(
    source.includes("$pairStmt->execute(['r'=>$roundId,'leader_entry'=>$entryId,'follower_entry'=>$entryId]);"),
    `${label} Final removal must bind both leader and follower entry placeholders`
  );
  assert(!source.includes('leader_entry_id=:e OR follower_entry_id=:e'), `${label} must not reuse a named placeholder`);
  const entryTable = pairTable.replace('_final_pairs', '_entries');
  assert(
    new RegExp(`UPDATE\\s+${entryTable}\\s+SET\\s+entry_status='withdrawn'`).test(source),
    `${label} must retain recoverable Final-only withdrawal`
  );
}

verifyFinalistRemoval(live, 'bdc_scoring_final_pairs', 'Live');
verifyFinalistRemoval(test, 'bdc_test_scoring_final_pairs', 'Test');
assert(version.build >= 3444, 'release metadata must include the v738 repair');

console.log('Finalist removal bindings v738 checks passed');
