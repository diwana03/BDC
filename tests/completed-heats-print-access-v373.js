const fs = require('fs');
const assert = require('assert');

const read = file => fs.readFileSync(file, 'utf8');
const liveManual = read('admin/scoring/core.php');
const testManual = read('admin/scoring-tests/index.php');
const liveAutomatic = read('admin/scoring/automatic-round.php');
const testAutomatic = read('admin/scoring-tests/automatic-inline.php');
const liveResult = read('admin/scoring/result.php');
const testResult = read('admin/scoring-tests/result.php');

for (const [name, source] of [['Live Manual', liveManual], ['Test Manual', testManual]]) {
  assert(source.includes("$round['status']==='completed'&&$round['round_type']==='heats'"), `${name} must expose the report only for completed Heats`);
  assert(source.includes('Completed Heats Score Report'), `${name} must label the persistent completed report`);
  assert(source.includes('View / Print Heats Scores'), `${name} must keep the completed report action visible`);
  assert(source.includes('href="result.php?round_id=<?=$roundId?>"'), `${name} must open the full score report`);
  assert(source.includes('Opening it does not reopen scoring.'), `${name} must explain the read-only behavior`);
}

for (const [name, source] of [['Live Automatic', liveAutomatic], ['Test Automatic', testAutomatic]]) {
  assert(source.includes("'completed'"), `${name} must recognize completed scoring`);
  assert(source.includes('Preview / Print Scores'), `${name} must retain score-report access`);
  assert(source.includes('result.php?round_id='), `${name} must open the score report`);
}

for (const [name, source] of [['Live result', liveResult], ['Test result', testResult]]) {
  assert(source.includes('Print / Save as PDF'), `${name} must retain browser PDF printing`);
}

console.log('Completed Heats score/PDF access is persistent and read-only across Test and Live.');
