'use strict';

const fs = require('fs');
const assert = require('assert');

const projector = fs.readFileSync('admin/dance-cup/projector.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

for (const marker of [
  'rows=Math.max(1,Math.ceil(page.length/5))',
  'contestant-grid rows-',
  '.contestant-grid.rows-1{grid-template-rows:minmax(0,1fr)!important}',
  '.contestant-grid.rows-2{grid-template-rows:repeat(2,minmax(0,1fr))!important}',
  '.contestant-grid>.contestant-card>div{height:100%;min-height:0;display:flex',
  '.contestant-photo-frame{flex:0 0 auto;display:block',
  'overflow:hidden;border-radius:50%',
  'transform:scale(1.16)',
  'object-position:50% 30%',
  '.contestant-grid.rows-2 .contestant-photo-frame{width:clamp(82px,min(7.2vw,10.5vh),118px)'
]) assert(projector.includes(marker), `missing adaptive portrait marker: ${marker}`);

assert(projector.includes('#app{width:100vw;height:100vh;padding:10vh 5vw'), 'universal projector safe area must remain unchanged');
assert(Number(version.version.match(/^2\.3\.6-dev(\d+)$/)?.[1]||0)>=714);
assert(version.build>=3420);

console.log('Dance Cup adaptive contestant portrait checks passed.');
