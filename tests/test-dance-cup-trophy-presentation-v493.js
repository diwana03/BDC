const fs = require('fs');
const assert = require('assert');

const projector = fs.readFileSync('admin/dance-cup/projector.php', 'utf8');
const trophy = 'public/assets/img/sbta-dance-cup-champion-trophy.png';

assert(fs.existsSync(trophy) && fs.statSync(trophy).size > 50000, 'exact branded SBTA trophy asset missing');
for (const marker of [
  'class="call-layout"',
  'class="contestant-hero"',
  'class="trophy-showcase"',
  'sbta-dance-cup-champion-trophy.png',
  'Dance Cup Champion Trophy',
  'width:clamp(180px,16vw,300px)',
  '.call .identity-meta .flag{font-size:2.15em',
  '.trophy-showcase{display:none}'
]) assert(projector.includes(marker), `premium contestant presentation missing: ${marker}`);

console.log('dev493 Dance Cup trophy presentation checks passed');
