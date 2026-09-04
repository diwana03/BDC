const fs = require('node:fs');
const assert = require('node:assert/strict');

const roster = fs.readFileSync('public/css/projector-roster-v615.css', 'utf8');
const feed = fs.readFileSync('live-display/feed.php', 'utf8');
const outer = fs.readFileSync('live-display/index.php', 'utf8');
const flights = fs.readFileSync('app/Services/ScoringFlightService.php', 'utf8');
const state = fs.readFileSync('live-display/state.php', 'utf8');

assert.match(roster, /\.stage \.competitor-country \{[\s\S]*?grid-column: 1 \/ -1;[\s\S]*?flex-direction: column/);
assert.match(roster, /\.stage \.competitor-country-name \{[\s\S]*?white-space: nowrap;[\s\S]*?word-break: normal/);
assert.match(roster, /\.stage \.flight-country span \{[\s\S]*?white-space: nowrap;[\s\S]*?word-break: normal/);
assert.match(roster, /\.stage \.judge-country-name \{[\s\S]*?white-space: nowrap;[\s\S]*?word-break: normal/);
assert.match(outer, /bdc-projector-roster-fix/);
assert.match(outer, /projector-roster-v615\.css\?v=63[4-9]|projector-roster-v615\.css\?v=6[4-9]\d/);
assert.match(outer, /function swapFeed\(url\)/);
assert.match(outer, /previous\.style\.visibility='hidden'/);
assert.match(outer, /roster\.addEventListener\('load',(reveal|resolve)/);
assert.match(flights, /\$balancedFlightCount=max\(1,\(int\)ceil\(\$largestRole\/\$flightSize\)\)/);
assert.match(flights, /\$roundSize=\$base\+\(\$flightNumber<=\$remainder\?1:0\)/);
assert.match(state, /ceil\(max\(\$roleCounts\)\/\$roleCapacity\)/);
assert.match(feed, /\$competitorRoleCapacity=\$competitorRolePaged\?15:/);

console.log('Projector roster, balanced rounds, page totals and silent refresh v634: PASS');
