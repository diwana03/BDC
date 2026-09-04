const fs = require('fs');
const assert = require('assert');
const read = file => fs.readFileSync(file, 'utf8');

const jjFeed = read('live-display/feed.php');
const safeCss = read('public/css/projector-safe-v616.css');
const danceCup = read('admin/dance-cup/projector.php');
const version = JSON.parse(read('VERSION.json'));

assert(jjFeed.includes('projector-safe-v616.css?v=616'), 'shared projector does not load v616 safe-area CSS');
assert(safeCss.includes('padding-top: 10cqh') && safeCss.includes('padding-bottom: 10cqh'), 'Jack & Jill 10% safe area missing');
assert(jjFeed.includes('window.parent.document.documentElement.requestFullscreen()'), 'Jack & Jill fullscreen action missing');
assert(danceCup.includes('document.documentElement.requestFullscreen()'), 'Dance Cup fullscreen action missing');
assert(danceCup.includes('padding:10vh '), 'Dance Cup 10% safe area missing');
assert(jjFeed.includes("preg_replace('/(^|_)rising$/', '$1intermediate'"), 'Jack & Jill public Intermediate mapping missing');
assert(danceCup.includes("replace(/\\brising\\b/gi,'Intermediate')"), 'Dance Cup public Intermediate mapping missing');
assert(danceCup.includes("const category=label(latest?.state?.category_name"), 'Dance Cup category public-label mapping missing');
assert.strictEqual(version.version, '2.3.3-dev616');
assert.strictEqual(version.build, 3322);
console.log('projector safe-area, fullscreen and public labels v616: PASS');
