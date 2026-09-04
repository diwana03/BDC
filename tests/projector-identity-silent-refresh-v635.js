const fs = require('node:fs');
const assert = require('node:assert/strict');

const outer = fs.readFileSync('live-display/index.php', 'utf8');
const css = fs.readFileSync('public/css/projector-roster-v615.css', 'utf8');

for (const marker of [
  'function normalizeRosterIdentity(target)',
  "identity.className='competitor-identity'",
  "identity.className='judge-identity'",
  "countryName.textContent=[...country.querySelectorAll('.judge-country-name')]",
  "roster.href='../public/css/projector-roster-v615.css?v=635'",
  "await Promise.race([Promise.all(images.map",
  "transition:'opacity 140ms ease'",
]) assert(outer.includes(marker), `missing active projector integration: ${marker}`);

for (const selector of [
  '.stage .competitor-identity',
  '.stage .competitor-flags',
  '.stage .judge-identity',
  '.stage .judge-flags',
]) assert(css.includes(selector), `missing projector identity selector: ${selector}`);

assert.match(css, /\.stage \.competitor-identity \.competitor-country-name,[\s\S]*?white-space: normal;[\s\S]*?word-break: normal;[\s\S]*?text-overflow: clip;/);
assert.match(css, /\.stage \.judge-identity \.judge-country-name[\s\S]*?white-space: normal;[\s\S]*?word-break: normal;[\s\S]*?text-overflow: clip;/);
assert(!outer.includes("location.reload()"), 'audience projector must not use full-page reload');

console.log('Projector aligned flags, full country names and asset-ready silent refresh v635: PASS');
