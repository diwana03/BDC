const fs = require('fs');
const assert = require('assert');

const judge = fs.readFileSync('admin/dance-cup/judge-scoring.php', 'utf8');
const css = fs.readFileSync('public/css/scoring-premium.css', 'utf8');

for (const marker of ['dc-panel-total-copy', " of '.$panelCount.' completed", 'dc-panel-total-track', '% complete', "aria-label=\"'.$submitted.' of '.$panelCount.' categories submitted"]) {
  assert(judge.includes(marker), `missing clear panel progress marker: ${marker}`);
}
for (const marker of ['/* dev486 readable judging-panel progress. */', '.dc-panel-total-track', '.dc-panel-total-copy strong span']) {
  assert(css.includes(marker), `missing readable panel progress style: ${marker}`);
}
assert(!judge.includes('dc-panel-total-ring'), 'cramped progress ring must be removed from markup');

console.log('dev486 readable panel progress checks passed');
