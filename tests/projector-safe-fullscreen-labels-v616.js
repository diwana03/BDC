const fs = require('fs');
const assert = require('assert');
const read = file => fs.readFileSync(file, 'utf8');

const jjFeed = read('live-display/feed.php');
const safeCss = read('public/css/projector-safe-v616.css');
const danceCup = read('admin/dance-cup/projector.php');
const jjControl = read('admin/live-screen/control.php');
const danceCupControl = read('admin/dance-cup/projection-control.php');
const version = JSON.parse(read('VERSION.json'));

assert(jjFeed.includes('projector-safe-v616.css?v=619'), 'shared projector does not load the refreshed safe-area CSS');
assert(safeCss.includes('padding-top: 10cqh') && safeCss.includes('padding-bottom: 10cqh'), 'Jack & Jill 10% safe area missing');
assert(!jjFeed.includes('requestFullscreen()'), 'Jack & Jill audience screen still contains fullscreen control');
assert(!danceCup.includes('requestFullscreen()'), 'Dance Cup audience screen still contains fullscreen control');
assert(jjControl.includes('data-fullscreen-control'), 'Jack & Jill control-panel fullscreen action missing');
assert(danceCupControl.includes('data-fullscreen-control'), 'Dance Cup control-panel fullscreen action missing');
assert(danceCup.includes('padding:10vh '), 'Dance Cup 10% safe area missing');
assert(jjFeed.includes("preg_replace('/(^|_)rising$/', '$1intermediate'"), 'Jack & Jill public Intermediate mapping missing');
assert(danceCup.includes("replace(/\\brising\\b/gi,'Intermediate')"), 'Dance Cup public Intermediate mapping missing');
assert(danceCup.includes("const category=label(latest?.state?.category_name"), 'Dance Cup category public-label mapping missing');
assert(/^2\.3\.3-dev\d+$/.test(version.version) && version.build >= 3322, 'version predates projector safe-area release');
console.log('projector safe-area, fullscreen and public labels v616: PASS');
