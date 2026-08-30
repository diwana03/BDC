const fs = require('fs');
const assert = require('assert');

const service = fs.readFileSync('app/Services/DanceCupScoringService.php', 'utf8');
const control = fs.readFileSync('admin/dance-cup/projection-control.php', 'utf8');
const feed = fs.readFileSync('admin/dance-cup/projection-feed.php', 'utf8');
const projector = fs.readFileSync('admin/dance-cup/projector.php', 'utf8');
const launch = fs.readFileSync('admin/dance-cup/projector-launch.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

for (const column of ['results_unlocked', 'reveal_place', 'effect_type', 'effect_version']) {
  assert(service.includes(column), `projection state must provision ${column}`);
}
assert(control.includes("$action==='unlock_results'"), 'control must explicitly unlock official results');
assert(control.includes("$action==='lock_results'"), 'control must relock and hold official results');
assert(control.includes("$action==='reveal_podium'"), 'control must provide progressive podium reveal commands');
assert(control.includes("['3'=>'Reveal 3rd','2'=>'Reveal 2nd','1'=>'Reveal Champion'"), 'podium must reveal third, second, then champion');
assert(control.includes('class="contestant-call"'), 'contestant calling must use the compact responsive selector');
assert(!control.includes('class="contestant-grid"'), 'wide contestant button wall must be removed');
assert(feed.includes("$publicResults=!empty($state['results_unlocked'])?$results:[]"), 'locked feed must redact scores and winners');
assert(projector.includes("type==='results'||type==='podium'"), 'projector must defend locked result screens');
assert(projector.includes('function playEffect'), 'projector must render presentation effects');
assert(projector.includes("reveal==='all'||Number(row.placement)>=Number(reveal)"), 'podium must progressively preserve revealed placements');
assert(projector.includes("<div class=\"holding\"><h2>"), 'holding state must contain only the event name');
assert(launch.includes('results_unlocked=0'), 'opening a projector must reset to locked Holding Screen');
assert.strictEqual(version.version, '2.3.3-dev522');

console.log('dev520 Dance Cup protected results reveal checks passed');
