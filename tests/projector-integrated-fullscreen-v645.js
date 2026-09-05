const fs = require('fs');

const shell = fs.readFileSync('live-display/index.php', 'utf8');
const feed = fs.readFileSync('live-display/feed.php', 'utf8');
const safe = fs.readFileSync('public/css/projector-safe-v616.css', 'utf8');

function requireText(source, value, label) {
  if (!source.includes(value)) throw new Error(`Missing ${label}`);
}

requireText(shell, 'function enterAudienceFullscreen()', 'outer fullscreen action');
requireText(shell, 'document.documentElement', 'outer projector document target');
requireText(shell, "badge.dataset.fullscreenReady='1'", 'feed-swap badge binding');
requireText(shell, "badge.addEventListener('click',enterAudienceFullscreen)", 'badge click action');
requireText(shell, "event.key==='Enter'||event.key===' '", 'keyboard action');
requireText(feed, 'BDC · Official Live Display', 'existing official badge');
requireText(safe, '[data-fullscreen-ready="1"]', 'interactive badge styling');
requireText(feed, 'projector-safe-v616.css?v=645', 'feed safe-style cache key');

console.log('Projector integrated fullscreen v645: PASS');
