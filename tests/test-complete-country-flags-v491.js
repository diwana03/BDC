const fs = require('fs');
const assert = require('assert');

const service = fs.readFileSync('app/Services/CountryFlagService.php', 'utf8');
const feed = fs.readFileSync('admin/dance-cup/projection-feed.php', 'utf8');
const countries = JSON.parse(fs.readFileSync('public/assets/flags/countries.json', 'utf8'));

for (const country of ['Chile', 'Nepal', 'India', 'Australia', 'New Zealand']) {
  const record = countries.find(item => item.name === country);
  assert(record && /^[a-z]{2}$/.test(record.code), `canonical flag code missing for ${country}`);
}

for (const marker of [
  "public/assets/flags/countries.json",
  "mb_strtolower(trim((string)$item['name']))",
  "strtoupper((string)$item['code'])",
  "'usa'=>'US'",
  "'uk'=>'GB'",
  "'korea'=>'KR'"
]) assert(service.includes(marker), `complete flag service missing: ${marker}`);

assert(!service.includes('private const MAP='), 'limited hardcoded country map must not remain');
assert(feed.includes("CountryFlagService::emoji($entry['country']??null)"), 'contestant projection must use the complete flag service');
assert(feed.includes("CountryFlagService::emoji($judge['country_code']?:($judge['country']??null))"), 'judge projection must use the complete flag service');

console.log('dev491 complete country flag checks passed');
