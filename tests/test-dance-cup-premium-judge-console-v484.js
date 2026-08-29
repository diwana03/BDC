const fs = require('fs');
const assert = require('assert');

const judge = fs.readFileSync('admin/dance-cup/judge-scoring.php', 'utf8');
const css = fs.readFileSync('public/css/scoring-premium.css', 'utf8');

assert(judge.includes('scoring-premium.css?v=484'), 'judge console must load the dev484 visual layer');
for (const marker of ['dc-premium-judge', 'dc-judge-identity-card', 'dc-scoring-progress-card', 'dc-empty-roster']) {
  assert(judge.includes(marker), `missing premium judge markup: ${marker}`);
  assert(css.includes(marker), `missing premium judge style: ${marker}`);
}
for (const preserved of ['id="dcJudgeProgress"', 'id="dcJudgeProgressBar"', 'id="dcJudgeScoreForm"', 'data-dc-judge-live', 'name="action" value="save"', 'name="action" value="submit"']) {
  assert(judge.includes(preserved), `scoring behavior hook changed: ${preserved}`);
}
assert(css.includes('/* dev484 premium Dance Cup judge console. */'));
assert(css.includes('@media(max-width:767.98px)'));
assert(judge.includes('<body class="dc-premium-judge dc-criteria-locked">'), 'criteria gate must remain injected into the premium body');

console.log('dev484 premium Dance Cup judge console checks passed');
