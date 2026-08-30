const fs = require('fs');
const assert = require('assert');

const feed = fs.readFileSync('admin/dance-cup/projection-feed.php', 'utf8');
assert(feed.includes("$fetch=function(string $sql,string $scope,array $params=[])"), 'feed queries must fail independently');
assert(feed.includes("'contestant-minimal'") && feed.includes("'judge-minimal'") && feed.includes("'result-minimal'"), 'all projector collections need schema-safe fallbacks');
assert(feed.includes('GROUP BY e.id,e.bib_number,e.display_name,c.country,c.photo_url'), 'mark aggregation must support ONLY_FULL_GROUP_BY production databases');
assert(feed.includes("error_log('BDC Dance Cup projector '"), 'fallback cause must remain available in server logs');
console.log('Dance Cup projection feed resilience v495 checks passed.');
