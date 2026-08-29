const fs = require('fs');
const assert = require('assert');

const page = fs.readFileSync('register/index.php', 'utf8');
const cup = fs.readFileSync('register/dance-cup-fields.php', 'utf8');

for (const marker of [
  "$formCompetitionType=in_array((string)($_POST['competition_type']??'')",
  "$formDanceStyle=in_array((string)($_POST['dance_style']??'')",
  "$formRole=in_array((string)($_POST['dance_role']??'')",
  "$formDivision=(string)($_POST['current_division']??'unknown')",
  "$formCountry=$normaliseCountry((string)($_POST['country']??''))",
  "$formValue=static fn(string $key)",
  "$formCompetitionType==='dance_cup'?'selected':''",
  "$formDanceStyle==='salsa'?'selected':''",
  "$formRole==='follower'?'selected':''",
  "$formDivision==='salsa_open'?'selected':''",
  "value=\"<?=e($formValue('full_name'))?>\"",
  "value=\"<?=e($formValue('phone_local'))?>\"",
  "<?=e($formValue('notes'))?>"
]) assert(page.includes(marker), `validation state is not preserved: ${marker}`);

for (const marker of ['$danceCupGender', '$danceCupStyles', '$danceCupEntries', '$danceCupLevels']) {
  assert(cup.includes(marker), `Dance Cup validation state missing: ${marker}`);
}

console.log('dev490 public competitor validation-state checks passed');
