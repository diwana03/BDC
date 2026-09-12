const fs = require('fs');

const projector = fs.readFileSync('admin/dance-cup/projector.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

for (const selector of [
  '.contestant-grid.rows-2>.contestant-card',
  '.contestant-grid.rows-2 .contestant-photo-frame',
  '.contestant-grid.rows-2 .kicker',
  '.contestant-grid.rows-2 .bib',
  '.contestant-grid.rows-2 .contestant-name',
  '.contestant-grid.rows-2 .identity-meta',
  '.contestant-grid.rows-2 .identity-country .flag-image',
]) assert(projector.includes(selector), `missing two-row rule: ${selector}`);

assert(projector.includes('font-size:clamp(40px,3.2vw,62px)!important;line-height:.82!important'), 'two-row contestant number is not vertically compact');
assert(projector.includes('font-size:clamp(15px,1.02vw,20px)!important'), 'two-row contestant name does not have a dedicated readable scale');
assert(projector.includes('font-size:clamp(10px,.68vw,14px)!important'), 'two-row country text does not have a dedicated scale');
assert(projector.includes('width:clamp(23px,1.55vw,30px)!important'), 'two-row flags are not height bounded');
assert(projector.includes('.contestant-grid.rows-1{grid-template-rows:minmax(0,1fr)!important}'), 'one-row contestant board changed');
assert(version.version.startsWith('2.3.6-dev') && version.build >= 3440, 'release metadata is older than dev734');

console.log('Dance Cup two row card scale v734 regression checks passed');
