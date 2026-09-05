const fs = require('node:fs');
const assert = require('node:assert/strict');

const outer = fs.readFileSync('live-display/index.php', 'utf8');
const css = fs.readFileSync('public/css/projector-roster-v615.css', 'utf8');

for (const marker of [
  "roster.href='../public/css/projector-roster-v615.css?v=641'",
  "await Promise.race([Promise.all(images.map",
  "transition:'opacity 140ms ease'",
]) assert(outer.includes(marker), `missing active projector integration: ${marker}`);

assert(!outer.includes('function normalizeRosterIdentity(target)'), 'outer projector must not rewrite native roster cards');
assert(css.includes('.stage .competitor-identity'), 'native feed identity wrapper must keep name and country together');
assert(!css.includes('.stage .judge-identity'), 'removed DOM-only judge layout must stay removed');
assert(!outer.includes("location.reload()"), 'audience projector must not use full-page reload');

console.log('Projector native cards and asset-ready silent refresh v636: PASS');
