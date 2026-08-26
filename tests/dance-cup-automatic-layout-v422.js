'use strict';

const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '..');
const read = relativePath => fs.readFileSync(path.join(root, relativePath), 'utf8');
const assert = (condition, message) => { if (!condition) throw new Error(message); };

const setup = read('admin/dance-cup/automatic-setup.php');
const bootstrap = read('bootstrap.php');
const manual = read('admin/dance-cup/category.php');

assert(setup.includes('data-bdc-nav-compact="1"'), 'Automatic setup must request compact universal navigation.');
assert(!setup.includes('>Back to Dashboard</a>'), 'The page must not add a duplicate dashboard action.');
assert(bootstrap.includes("compactNavigation=pageNavbar&&pageNavbar.getAttribute('data-bdc-nav-compact')==='1'"), 'Universal navigation must detect compact pages.');
assert(bootstrap.includes("if(!compactNavigation)controls.appendChild(button('Back','back'))"), 'Compact pages must omit the redundant universal Back button.');
assert(setup.includes('dc-auto-subnav') && setup.includes('Automatic Categories') && setup.includes('Scoring Options'), 'Workflow navigation must remain available below the header.');
assert(setup.includes("$workflowHref='workflow.php?workflow=automatic'.($test?'&data_mode=test':'')") && setup.includes("$scoringHref='./'.($test?'?data_mode=test':'')"), 'Rendered Test and Live workflow links must be transformed without unresolved PHP template text.');
assert(setup.includes('grid-template-columns:92px minmax(250px,1fr) auto'), 'Competitor number, database search and Add action need a spacious desktop grid.');
assert(setup.includes('grid-template-columns:minmax(250px,1fr) auto auto'), 'Judge search, Chief toggle and Add action need a spacious desktop grid.');
assert(setup.includes('@media(max-width:767px)') && setup.includes('grid-template-columns:1fr'), 'Roster forms must stack cleanly on mobile.');
assert(setup.includes('dance-cup-directory.js?v=426') && manual.includes('dance-cup-directory.js?v=426'), 'Manual and Automatic must share the current directory client cache.');
assert(setup.includes('data-directory-type="competitor"') && setup.includes('data-directory-type="judge"'), 'Layout cleanup must preserve both database suggestion fields.');
const directory = read('public/js/dance-cup-directory.js');
const theme = read('public/assets/css/bdc-theme.css');
assert(directory.includes("wrap.classList.add('dc-directory-open')") && directory.includes("card?.classList.add('dc-directory-card-open')"), 'Both suggestion menus must raise their active field and card.');
assert(theme.includes('.dc-directory-card-open{position:relative!important;z-index:1085!important;overflow:visible!important}'), 'Open contestant and judge menus must not be clipped or painted behind adjacent cards.');

console.log('Dance Cup Automatic clean responsive layout v422: PASS');
