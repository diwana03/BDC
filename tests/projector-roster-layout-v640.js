const fs = require('fs');
const css = fs.readFileSync('public/css/projector-roster-v615.css', 'utf8');
const feed = fs.readFileSync('live-display/feed.php', 'utf8');

function requireText(source, text, label) {
  if (!source.includes(text)) throw new Error(`Missing ${label}`);
}

requireText(css, '.stage .competitor-country {', 'competitor country rule');
requireText(css, 'grid-column: 3;', 'right identity column');
requireText(css, '.stage .list.judge-list.judge-count-11', 'eleven-judge layout');
requireText(css, 'grid-column: 2 / span 3;', 'centred final judge row start');
requireText(css, 'overflow-wrap: normal;', 'whole-word judge names');
requireText(feed, "' judge-list judge-count-' . count($items)", 'runtime judge count class');
requireText(feed, 'projector-roster-v615.css?v=644', 'roster cache version');

console.log('Projector roster layout v640: PASS');
