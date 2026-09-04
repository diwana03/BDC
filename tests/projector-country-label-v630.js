const fs = require('fs');
const assert = require('assert');

const css = fs.readFileSync('public/css/projector-roster-v615.css', 'utf8');
const feed = fs.readFileSync('live-display/feed.php', 'utf8');

assert(css.includes('.judge-country-entry:only-child'), 'Single-country judge layout override is missing.');
assert(css.includes('width: auto;'), 'Single-country judge label must use its natural width.');
assert(css.includes('white-space: nowrap;'), 'Single-country judge name must remain on one line.');
assert(css.includes('overflow-wrap: normal;'), 'Country labels must not break inside words.');
assert(css.includes('word-break: normal;'), 'Country word-breaking must remain disabled.');
assert(feed.includes('projector-roster-v615.css?v=630'), 'Projector stylesheet cache key was not advanced.');

console.log('Projector country label v630: PASS');
