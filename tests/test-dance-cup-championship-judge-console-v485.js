const fs = require('fs');
const assert = require('assert');

const judge = fs.readFileSync('admin/dance-cup/judge-scoring.php', 'utf8');
const css = fs.readFileSync('public/css/scoring-premium.css', 'utf8');

for (const marker of ['dc-judge-brand', 'bdc-logo-header.png', 'OFFICIAL JUDGING WORKSPACE', 'dc-panel-total-copy', 'dc-category-copy', 'dc-waiting-visual', 'Secure judge session active']) {
  assert(judge.includes(marker), `missing championship judge console marker: ${marker}`);
}
for (const marker of ['--dc-champagne', '.dc-judge-brand', '.dc-panel-category-grid::before', '.dc-waiting-ring', '.dc-refresh-button']) {
  assert(css.includes(marker), `missing championship visual treatment: ${marker}`);
}
for (const preserved of ['id="dcJudgeProgress"', 'id="dcJudgeProgressBar"', 'id="dcJudgeScoreForm"', 'data-dc-judge-live', 'name="action" value="save"', 'name="action" value="submit"']) {
  assert(judge.includes(preserved), `scoring behavior hook changed: ${preserved}`);
}

console.log('dev485 championship Dance Cup judge console checks passed');
