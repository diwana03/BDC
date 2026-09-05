const fs = require('fs');

const css = fs.readFileSync('public/css/projector-roster-v615.css', 'utf8');
const feed = fs.readFileSync('live-display/feed.php', 'utf8');
const shell = fs.readFileSync('live-display/index.php', 'utf8');

function has(value, label) {
  if (!css.includes(value)) throw new Error(`Missing ${label}`);
}

has('justify-content: center;', 'centred country row');
has('.stage .judge-country-entry:only-child {\n  width: 100%;', 'full-width single country');
has('margin-inline: auto !important;', 'centred judge flag');
has('white-space: nowrap !important;', 'one-line judge names');
if (!feed.includes('projector-roster-v615.css?v=644')) throw new Error('Feed cache key is stale');
if (!shell.includes("projector-roster-v615.css?v=644")) throw new Error('Shell cache key is stale');

console.log('Projector judge identity alignment v644: PASS');
