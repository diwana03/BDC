const fs = require('fs');

const feed = fs.readFileSync('live-display/feed.php', 'utf8');
const shell = fs.readFileSync('live-display/index.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

assert(feed.includes('class="projection-official holding-official"'), 'Holding Screen fullscreen badge is missing');
assert(feed.includes('>BDC · Official Live Display</button>'), 'Holding Screen badge label is incorrect');
assert(feed.includes('title="Enter full screen" aria-label="Enter full screen"'), 'Holding Screen badge lacks accessible fullscreen labeling');
assert(feed.includes('.holding-official{position:absolute;z-index:4;top:'), 'Holding Screen badge is not positioned at the top');
assert(feed.includes('right:clamp(22px,3vw,64px)'), 'Holding Screen badge lacks a safe right margin');
assert(feed.includes('cursor:pointer'), 'Holding Screen badge does not advertise click interaction');
assert(shell.includes("doc.querySelectorAll('.projection-official').forEach"), 'shared fullscreen badge binding is missing');
assert(shell.includes("badge.addEventListener('click',enterAudienceFullscreen)"), 'Holding Screen badge is not connected to fullscreen');
assert(shell.includes("event.key==='Enter'||event.key===' '"), 'fullscreen control lacks keyboard activation');
assert(version.version === '2.3.6-dev730' && version.build === 3436, 'release metadata mismatch');

console.log('Holding Screen fullscreen control v730 checks passed');
