const fs = require('fs');

const feed = fs.readFileSync('live-display/feed.php', 'utf8');
const css = fs.readFileSync('public/css/projector-safe-v616.css', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

assert(feed.includes('$judgePaginated = $judgePages > 1;'), 'judge pagination state is not exposed');
assert(feed.includes("($judgePaginated ? ' judge-paginated' : '')"), 'paginated judge list class is missing');
assert(css.includes('.judge-list.judge-paginated{'), 'stable paginated judge grid is missing');
assert(css.includes('grid-template-columns:repeat(8,minmax(0,1fr))!important'), 'paginated judge grid is not fixed to four card tracks');
assert(css.includes('.judge-paginated.judge-count-6>.judge-card:nth-last-child(2)'), 'six-judge final row is not centred');
assert(css.includes('.judge-paginated.judge-count-7>.judge-card:nth-last-child(3)'), 'seven-judge final row is not centred');
assert(css.includes('height:clamp(153px,min(48.75cqw,60cqh),193px)!important'), 'stable 4:5 judge portrait height is missing');
assert(css.includes('grid-template-rows:minmax(0,1fr) auto auto!important'), 'photo, name and country do not have separate rows');
assert(css.includes('.competitor-role-grid:not(:has(>.competitor-card:nth-child(7)))'), 'sparse competitor layout is missing');
assert(css.includes('text-overflow:clip!important'), 'sparse competitor names still force ellipsis');
assert(Number(version.version.match(/dev(\d+)$/)?.[1] || 0) >= 729 && version.build >= 3435, 'release metadata predates dev729');

console.log('projector adaptive card sizing v729 regression checks passed');
