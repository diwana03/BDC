const fs = require('fs');
const assert = require('assert');

const page = fs.readFileSync('register/index.php', 'utf8');

for (const marker of [
  'public/assets/flags/countries.json',
  "'Chile'=>'+56'",
  'name="country" id="portalCountry"',
  'name="phone_dial_code" id="portalDial"',
  'name="phone_local"',
  'item.dataset.country===country',
  "'p'=>$submittedPhone",
  "'c'=>$submittedCountry?:null"
]) assert(page.includes(marker), `missing public portal country/phone feature: ${marker}`);

assert(!/<input[^>]+name="country"/.test(page), 'public Country must not remain free text');
assert(!/<input[^>]+name="phone"/.test(page), 'public Phone must not remain one unstructured field');
assert(page.includes("$mode=($_GET['mode']??$_POST['mode']??'new')==='update'?'update':'new'"), 'new/update mode workflow changed');

console.log('dev489 public competitor country and phone selector checks passed');
