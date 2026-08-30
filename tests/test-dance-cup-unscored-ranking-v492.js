const fs = require('fs');
const assert = require('assert');

const feed = fs.readFileSync('admin/dance-cup/projection-feed.php', 'utf8');
const projector = fs.readFileSync('admin/dance-cup/projector.php', 'utf8');

for (const marker of [
  '1 has_score',
  'COUNT(m.criterion_id) mark_count',
  "(COUNT(m.criterion_id)>0) DESC",
  "$row['has_score']=(int)$row['mark_count']>0?1:0",
  "$row['placement']=null",
  '$rankedCount++'
]) assert(feed.includes(marker), `unscored ranking safeguard missing: ${marker}`);

for (const marker of [
  "const scored=Number(row.has_score)===1",
  "(scored?esc(row.placement):'—')",
  "(scored?Number(row.total_score)",
  ":'Not scored')",
  "data.results.filter(row=>Number(row.has_score)===1&&row.placement!==null)"
]) assert(projector.includes(marker), `projector unscored state missing: ${marker}`);

console.log('dev492 Dance Cup unscored ranking checks passed');
