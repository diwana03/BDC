const fs = require('fs');

const feed = fs.readFileSync('live-display/feed.php', 'utf8');
const state = fs.readFileSync('live-display/state.php', 'utf8');
const display = fs.readFileSync('live-display/index.php', 'utf8');
const css = fs.readFileSync('public/css/projector-roster-v615.css', 'utf8');

function has(source, value, label) {
  if (!source.includes(value)) throw new Error(`Missing ${label}`);
}

has(feed, '$flightDisplayPage=$isFlightRoster?max(1,(int)($_GET["display_page"]??1)):1;', 'flight display subpage');
has(feed, '$competitorRoleCapacity=($competitorRolePaged||$isFlightRoster)?15', '15-person Flight Round cap');
has(feed, 'array_slice($roleItems,($competitorRolePage-1)*$competitorRoleCapacity,$competitorRoleCapacity)', 'role slice');
has(state, '$s["screen_type"] === "flights"', 'Flight Round state pagination');
has(state, 'ceil(max($roleCounts) / 15)', 'Flight Round page count');
has(display, "s.screen_type==='flights'?flightDisplayPage:1", 'flight page fingerprint');
has(display, "flightDisplayPage=(flightDisplayPage%s.total_pages)+1", 'silent local Flight Round cycling');
has(css, 'white-space: nowrap !important;', 'one-line names');
has(css, 'width: clamp(30px, min(15cqw, 13cqh), 48px) !important;', 'larger competitor flags');
has(css, 'width: clamp(28px, min(17cqw, 16cqh), 50px) !important;', 'larger judge flags');

console.log('Projector Flight Round roster integrity v643: PASS');
