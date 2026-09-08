const fs = require('fs');
const assert = require('assert');
const read = file => fs.readFileSync(file, 'utf8');

const jjFeed = read('live-display/feed.php');
const safeCss = read('public/css/projector-safe-v616.css');
const danceCup = read('admin/dance-cup/projector.php');
const jjControl = read('admin/live-screen/control.php');
const danceCupControl = read('admin/dance-cup/projection-control.php');
const version = JSON.parse(read('VERSION.json'));

assert.match(jjFeed, /projector-safe-v616\.css\?v=<\?=/, 'shared projector does not load dynamic safe-area CSS');
assert(safeCss.includes('padding-top: 10cqh') && safeCss.includes('padding-bottom: 10cqh'), 'Jack & Jill 10% safe area missing');
assert(!jjFeed.includes('requestFullscreen()'), 'Jack & Jill audience screen still contains fullscreen control');
assert(!danceCup.includes('requestFullscreen()'), 'Dance Cup audience screen still contains fullscreen control');
assert(jjControl.includes('data-fullscreen-control'), 'Jack & Jill control-panel fullscreen action missing');
assert(danceCupControl.includes('data-fullscreen-control'), 'Dance Cup control-panel fullscreen action missing');
assert(danceCup.includes('#app{width:100vw;height:100vh;padding:10vh 5vw'), 'Dance Cup 10% vertical and 5% horizontal safe area missing');
assert(jjFeed.includes("preg_replace('/(^|_)rising$/', '$1intermediate'"), 'Jack & Jill public Intermediate mapping missing');
assert(danceCup.includes("replace(/\\brising\\b/gi,'Intermediate')"), 'Dance Cup public Intermediate mapping missing');
assert(danceCup.includes("const category=label(latest?.state?.category_name"), 'Dance Cup category public-label mapping missing');
assert(/^2\.3\.\d+-dev\d+$/.test(version.version) && Number(version.version.match(/dev(\d+)$/)?.[1] || 0) >= 616 && version.build >= 3322, 'version predates projector safe-area release');
console.log('projector safe-area, fullscreen and public labels v616: PASS');
