const fs = require('fs');

const wrapper = fs.readFileSync('live-display/index.php', 'utf8');
const safe = fs.readFileSync('public/css/projector-safe-v616.css', 'utf8');

const requiredCodes = [
  "DO:'DOM'",
  "SE:'SWE'",
  "CO:'COL'",
  "ES:'ESP'",
  "CH:'SUI'",
  "KR:'KOR'",
  "AU:'AUS'",
  "RU:'RUS'",
];
for (const code of requiredCodes) {
  if (!wrapper.includes(code)) throw new Error(`Missing projector display code ${code}`);
}

if (!wrapper.includes("safe.href='../public/css/projector-safe-v616.css?v=676'")) {
  throw new Error('Outer projector wrapper must force-refresh the safe stylesheet.');
}
if (!wrapper.includes("roster.href='../public/css/projector-roster-v615.css?v=649'")) {
  throw new Error('Outer projector wrapper must force-refresh the roster stylesheet.');
}
if (!wrapper.includes("querySelectorAll('.judge-country-entry').forEach(compactCountryLabel)")) {
  throw new Error('Judge country labels must be converted after every feed load.');
}
if (!wrapper.includes("match(/\\/([a-z]{2})\\.svg")) {
  throw new Error('Judge country codes must derive from the rendered flag ISO2 path, not clipped country text.');
}
if (!safe.includes('margin-bottom:clamp(20px,3.2cqh,46px)')) {
  throw new Error('Shared projector heading must reserve the approved two-line breathing gap.');
}
if (!safe.includes('flex-wrap:nowrap !important')) {
  throw new Error('Judge flag/code strip must stay on one row.');
}
if (/Dominican Republic[^\n]*content\s*:|Switzerland[^\n]*content\s*:|South Korea[^\n]*content\s*:/.test(safe)) {
  throw new Error('Country display codes must not be faked with CSS name selectors.');
}

console.log('projector judge country runtime v648: PASS');
