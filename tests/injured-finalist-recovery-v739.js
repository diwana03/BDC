const fs = require('fs');
const assert = require('assert');

const service = fs.readFileSync('app/Services/InjuredFinalistRecoveryService.php', 'utf8');
const backup = fs.readFileSync('app/Services/ScoringBackupService.php', 'utf8');
const live = fs.readFileSync('admin/scoring/core.php', 'utf8');
const test = fs.readFileSync('admin/scoring-tests/index.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

function verifyController(source, label, testMode) {
  assert(source.includes("$action==='recover_injured_finalist'"), `${label} must route the protected recovery action`);
  assert(source.includes('Auth::canOverrideCompletedScores()'), `${label} must enforce the scoring override role`);
  assert(source.includes(`recover($pdo,$roundId,(int)($_POST['entry_id']??0),${testMode}`), `${label} must use the correct isolated data mode`);
  assert(source.includes('class="row g-2 finalist-recovery-form"'), `${label} must expose the recovery form`);
  assert(source.includes('name="recovery_confirmation"'), `${label} must require typed confirmation`);
  assert(source.includes('name="promote_next" value="1" checked'), `${label} must offer next-ranked replacement by default`);
  assert(source.includes(':not(.finalist-recovery-form)'), `${label} must keep recovery usable on a completed Final`);
  assert(source.includes("'recover_injured_finalist','create_scoring_backup'"), `${label} locked-round guard must delegate recovery to the protected service`);
  assert(source.includes("ScoringBackupService::create($pdo,(int)$_POST['round_id']"), `${label} must create the automatic pre-action backup`);
}

verifyController(live, 'Live', 'false');
verifyController(test, 'Test', 'true');

assert(service.includes("'WITHDRAW FINALIST'"), 'service must require exact protected confirmation');
assert(service.includes("status IN('scoring','submitted')"), 'service must verify the Final is actually scoring-locked');
assert(service.includes("entry_status='withdrawn'"), 'service must use recoverable Final-only withdrawal');
assert(service.includes("DELETE FROM {$t['final_results']} WHERE round_id=:round"), 'service must clear current-Final results');
assert(service.includes("DELETE FROM {$t['marks']} WHERE round_id=:round"), 'service must clear current-Final marks');
assert(service.includes("DELETE FROM {$t['pairs']} WHERE round_id=:round"), 'service must clear current-Final pairings');
assert(!service.includes("DELETE FROM {$t['results']}"), 'service must never delete previous-round results');
assert(service.includes("status='not_started'"), 'service must reopen Final judge sessions');
assert(service.includes("status='revoked'"), 'service must revoke the old Emcee link');
assert(service.includes("screen_type='holding'"), 'service must return projection to Holding');
assert(service.includes("'injured_finalist_recovered'"), 'service must create an audit record');
assert(service.includes('FOR UPDATE'), 'service must lock recovery targets transactionally');
assert(service.includes('NOT EXISTS(SELECT 1'), 'service must not duplicate an existing finalist');
assert(service.includes('ORDER BY sr.rank_number ASC'), 'service must choose the next-ranked replacement deterministically');

assert(backup.includes("'entries'=>$p.'entries'"), 'backup snapshot must include the Final roster');
assert(backup.includes("if($hasEntrySnapshot){$deleteOrder[]='entries';array_unshift($insertOrder,'entries');}"), 'restore must recover roster entries while remaining compatible with older backups');
assert(version.build >= 3445, 'release metadata must include the v739 recovery');

console.log('Injured finalist recovery v739 checks passed');
