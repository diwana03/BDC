const fs = require('fs');
const assert = require('assert');

const control = fs.readFileSync('admin/dance-cup/projection-control.php', 'utf8');
const feed = fs.readFileSync('admin/dance-cup/projection-feed.php', 'utf8');
const projector = fs.readFileSync('admin/dance-cup/projector.php', 'utf8');
const tie = fs.readFileSync('app/Services/DanceCupTieService.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

assert(control.includes('use App\\Services\\DanceCupTieService;'), 'projection control must load the canonical tie service');
assert(control.match(/DanceCupTieService::hasUnresolved\(\$pdo,\$id,\$test\)/g).length >= 4, 'render, unlock, reveal and live scoreboard must share the tie gate');
assert(control.includes('Chief Judge decision required.'), 'operator must see why the podium is protected');
assert(control.includes('Resolve the exact-score tie'), 'operator must receive a direct recovery route');
assert(control.includes("$resultRevealAuthorized=!empty($state['results_unlocked'])&&!$unresolvedPodiumTies;"), 'operator controls must use the combined unlock and tie gate');
assert(control.includes("$unresolvedPodiumTies?'Resolve tie first':'Unlock first'"), 'live scoreboard control must explain the blocked state');
assert(control.includes("$unresolvedPodiumTies?'disabled':''"), 'all progressive podium buttons must be disabled during unresolved ties');
assert(feed.includes('$resultRevealReady=!DanceCupTieService::hasUnresolved($pdo,$competition,$test);'), 'feed must independently verify tie resolution');
assert(feed.includes("!empty($state['results_unlocked'])&&$resultRevealReady?$results:[]"), 'feed must redact unresolved winner data');
assert(feed.includes("'result_reveal_ready'=>$resultRevealReady?1:0"), 'feed must expose the protected reveal state');
assert(projector.includes('!Number(data.result_reveal_ready)'), 'projector must force unresolved result screens to Holding');
assert(tie.includes("UPDATE {$p}_scoring_results SET placement=:place"), 'Chief Judge resolution must save unique final placements without altering scores');
assert(tie.includes("UPDATE {$p}_scoring_results SET placement=:p"), 'mobile Chief Judge resolution must save unique final placements without altering scores');
assert(version.build >= 3446, 'release metadata must include dev740 or later');

console.log('Dance Cup tie-safe podium v740 checks passed');
