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
  "const eventLabel=latest?.state?.event_name||'Dance Cup'",
  "<strong>'+esc(eventLabel)+'</strong>",
  'width:clamp(180px,16vw,300px)',
  '.call .identity-meta{font-size:clamp(30px,2.8vw,52px)',
  '.call .identity-meta .flag{font-size:1.7em',
  '@media(max-width:640px){.call-layout{grid-template-columns:1fr}.trophy-showcase{display:none}}'
]) assert(projector.includes(marker), `premium contestant presentation missing: ${marker}`);

assert(projector.includes('@media(max-width:640px){.call-layout{grid-template-columns:1fr}.trophy-showcase{display:none}}'), 'trophy may hide only on genuinely narrow screens');
assert(!projector.includes('Dance Cup Champion Trophy</strong>'), 'trophy panel must identify the event instead of claiming the contestant is champion');
assert(!/@media\(max-aspect-ratio:1\/1\)[^\n]*trophy-showcase\{display:none\}/.test(projector), 'portrait projector layout must retain the trophy');

console.log('dev496 Dance Cup trophy presentation checks passed');
