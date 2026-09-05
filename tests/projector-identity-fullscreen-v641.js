const fs = require('fs');
const feed = fs.readFileSync('live-display/feed.php', 'utf8');
const css = fs.readFileSync('public/css/projector-roster-v615.css', 'utf8');
const workspace = fs.readFileSync('admin/live-screen/projection-workspace.php', 'utf8');
const audience = fs.readFileSync('live-display/index.php', 'utf8');

function requireText(source, text, label) {
  if (!source.includes(text)) throw new Error(`Missing ${label}`);
}

requireText(feed, 'class="competitor-identity"', 'structural competitor identity');
requireText(feed, 'projector-roster-v615.css?v=643', 'fresh roster stylesheet');
requireText(feed, 'id="bdc-projector-roster-fix"', 'single active roster stylesheet id');
requireText(audience, "roster.href='../public/css/projector-roster-v615.css?v=643'", 'outer-shell roster parity');
requireText(css, '.stage .competitor-identity {', 'responsive identity layout');
requireText(css, 'width: clamp(30px, min(15cqw, 13cqh), 48px)', 'larger responsive flag');
requireText(css, 'white-space: nowrap !important;', 'whole judge name');
requireText(workspace, 'data-fullscreen-control', 'workspace Full Screen control');
requireText(workspace, 'projection-control-fullscreen-v618.js?v=641', 'workspace Full Screen script');
if (audience.includes('data-fullscreen-control')) throw new Error('Audience display must not expose projector controls');

console.log('Projector identity and Full Screen v641: PASS');
