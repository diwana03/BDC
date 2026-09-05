const assert = require('node:assert/strict');
const fs = require('node:fs');

const feed = fs.readFileSync('live-display/feed.php', 'utf8');

const liveScoresStart = feed.indexOf('<?php elseif ($type === "heats_scores"): ?>');
const liveScoresEnd = feed.indexOf('<?php elseif (', liveScoresStart + 1);
assert(liveScoresStart >= 0 && liveScoresEnd > liveScoresStart, 'Live Contestant Scores renderer missing');
const liveScores = feed.slice(liveScoresStart, liveScoresEnd);

assert.match(feed, /c\.country,c\.countries_json[\s\S]*GROUP BY[^\n]*c\.country,c\.countries_json/,
  'Live Contestant Scores query must load every configured country');
assert.match(liveScores, /class="live-score-name"[^>]*><\?= e\(\(string\) \$x\["display_name"\]\) \?>/,
  'contestant name must render independently from country');
assert.match(liveScores, /live-score-name[\s\S]*live-score-flags[\s\S]*live-score-flag/,
  'real flag images must render after the contestant name');
assert.match(liveScores, /CountrySetService::fromRow\(\$x\)/,
  'all configured contestant countries must be supported');
assert.doesNotMatch(feed, /elseif \(\$type === "heats_scores"\) \{\s*foreach \(\$items as &\$projectionItem\)[\s\S]{0,300}CountryFlagService::emoji/,
  'country emoji codes must not be prepended to Live Contestant Scores names');
assert.match(liveScores, /\.live-score-name\{font-size:max\(13px,min\(1\.28cqw,2\.25cqh\)\)/,
  'contestant name must use venue-readable responsive sizing');
assert.match(liveScores, /white-space:nowrap;overflow:hidden;text-overflow:ellipsis/,
  'contestant name must remain on one line');

console.log('OK: Live Contestant Scores uses larger one-line names with real flags after the name.');
