const fs = require('fs');
const assert = require('assert');

const projector = fs.readFileSync('admin/dance-cup/projector.php', 'utf8');
const trophy = 'public/assets/img/sbta-dance-cup-champion-trophy.png';

assert(fs.existsSync(trophy) && fs.statSync(trophy).size > 50000, 'exact branded SBTA trophy asset missing');
for (const marker of [
  'class="call-layout"',
  'class="number-panel"',
  'class="contestant-identity"',
  'Contestant Number',
  'class="trophy-showcase"',
  'sbta-dance-cup-champion-trophy.png',
  "const eventLabel=latest?.state?.event_name||'Dance Cup'",
  "<strong>'+esc(eventLabel)+'</strong>",
  'width:clamp(230px,19vw,360px)',
  '.call .identity-meta{font-size:clamp(34px,3vw,56px)',
  '.call .identity-meta .flag{font-size:1.75em',
  '@media(max-width:640px){.call-layout{height:auto;grid-template-columns:minmax(105px,.55fr) minmax(0,1.45fr)}.trophy-showcase{display:none}'
]) assert(projector.includes(marker), `premium contestant presentation missing: ${marker}`);

assert(projector.includes('grid-template-columns:minmax(190px,.72fr) minmax(390px,1.35fr) minmax(240px,.82fr)'), 'landscape call must keep three readable presentation sections');
assert(!projector.includes('Dance Cup Champion Trophy</strong>'), 'trophy panel must identify the event instead of claiming the contestant is champion');
assert(!/@media\(max-aspect-ratio:1\/1\)[^\n]*trophy-showcase\{display:none\}/.test(projector), 'portrait projector layout must retain the trophy');

console.log('dev498 Dance Cup three-section presentation checks passed');
