const fs = require('fs');
const assert = require('assert');

const edit = fs.readFileSync('admin/competitors/edit.php', 'utf8');
const countries = JSON.parse(fs.readFileSync('public/assets/flags/countries.json', 'utf8'));

assert(countries.some(country => country.name === 'Chile'), 'canonical country data must include Chile');
for (const marker of [
  "public/assets/flags/countries.json",
  "'Chile'=>'+56'",
  'name="country" id="competitorCountry"',
  'name="phone_dial_code" id="competitorDial"',
  'name="phone_local"',
  'data-country="<?=e($dialCountry)?>"',
  'strcasecmp(trim($country),$option)===0',
  "str_starts_with($localPhone,'+')",
  "trim($dialCode.' '.$localPhone)",
  'item.dataset.country===country'
]) assert(edit.includes(marker), `missing country/phone selector safeguard: ${marker}`);

assert(!/<input[^>]+name="country"/.test(edit), 'Country must not remain a free-text input');
assert(!/<input[^>]+name="phone"/.test(edit), 'Phone must not remain one unstructured input');

console.log('dev488 competitor country and phone selector checks passed');
