const fs = require('node:fs');
const assert = require('node:assert/strict');

const css = fs.readFileSync('public/css/projector-roster-v615.css', 'utf8');
const outer = fs.readFileSync('live-display/index.php', 'utf8');
const feed = fs.readFileSync('live-display/feed.php', 'utf8');

assert.match(css, /@container \(aspect-ratio < 9\/10\) \{/);
assert.match(css, /\.stage \.list > \.judge-card:only-child \{[\s\S]*?grid-template-columns: minmax\(0, 1fr\) !important;[\s\S]*?grid-template-rows: auto minmax\(0, 1fr\) auto auto auto !important;/);
assert.match(css, /\.stage \.list > \.judge-card:only-child \.judge-photo-frame \{[\s\S]*?grid-column: 1;[\s\S]*?grid-row: 2;/);
assert.match(css, /\.stage \.list > \.judge-card:only-child \.judge-name \{[\s\S]*?grid-row: 3;/);
assert.match(css, /\.stage \.list > \.judge-card:only-child \.judge-country-name \{[\s\S]*?white-space: normal;[\s\S]*?word-break: normal;/);
assert(!outer.includes('projectorFullscreen'), 'Audience projector must not render a fullscreen button');
assert(!outer.includes('PRESS F11 FOR FULL SCREEN'), 'Audience projector must not show desktop F11 text on mobile');
assert(outer.includes("projector-roster-v615.css?v=643"), 'Mobile stylesheet cache key is stale');
assert(feed.includes('$competitorRolePage=$isFlightRoster?min($flightDisplayPage,$competitorRoleTotalPages):'), 'Flight number must not be reused as a roster page offset');
assert(feed.includes('$flightDisplayPage=$isFlightRoster?max(1,(int)($_GET["display_page"]??1)):1;'), 'Flight contestants need an independent display page');

console.log('Projector portrait judge call and Flight Round integrity v637: PASS');
