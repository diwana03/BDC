'use strict';

const fs = require('fs');
const assert = require('assert');

const css = fs.readFileSync('public/css/projector-safe-v616.css', 'utf8');
const feed = fs.readFileSync('live-display/feed.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

assert(feed.includes('class="final-couple-person final-couple-leader"'), 'Final matrix must identify the Lead group');
assert(feed.includes('class="final-matrix-amp">&amp;</span>'), 'Final matrix must keep the ampersand between Lead and Follow');
assert(feed.includes('class="final-couple-person final-couple-follower"'), 'Final matrix must identify the Follow group');
assert(css.includes('.final-couple-leader{\n  justify-content:flex-end!important;'), 'Lead must hug the left side of the ampersand');
assert(css.includes('.final-couple-follower{\n  justify-content:flex-start!important;'), 'Follow must hug the right side of the ampersand');
assert(css.includes('.final-couple-person>.final-couple-name{\n  flex:0 1 auto!important;\n  width:auto!important;'), 'Names must use natural width and remain shrinkable');
assert(Number(version.version.match(/^2\.3\.6-dev(\d+)$/)?.[1]||0)>=709);
assert(version.build>=3415);

console.log('Final score matrix A & B spacing checks passed.');
