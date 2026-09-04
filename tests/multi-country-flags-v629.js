const fs=require('fs');
const assert=require('assert');
const read=file=>fs.readFileSync(file,'utf8');

const migration=read('database/migrations/20260904_0300_multi_country_flags.php');
for(const table of ['bdc_competitors','bdc_test_competitors','bdc_judges','bdc_wdc_identities'])assert(migration.includes(`'${table}'`),`${table} migration missing`);

const service=read('app/Services/CountrySetService.php');
assert(service.includes('MAX_COUNTRIES=5'),'five-country cap missing');
assert(service.includes("$row['country']"),'primary country compatibility missing');

const feed=read('live-display/feed.php');
assert(feed.includes('j.countries_json'),'judge country set not loaded');
assert(feed.includes('c.countries_json'),'Test/Live competitor country set not loaded');
assert(feed.includes('judge-country-entry'),'multi-flag judge renderer missing');

const css=read('public/css/projector-roster-v615.css');
assert(css.includes('.judge-country-entry'),'judge flag alignment rule missing');
assert(css.includes('width: min(82%, 1180px)'),'single-judge safe-area width missing');

const dcFeed=read('admin/dance-cup/projection-feed.php');
const dcProjector=read('admin/dance-cup/projector.php');
assert(dcFeed.includes("$judge['flags']"),'Dance Cup judge flags missing');
assert(dcFeed.includes("$entry['flags']"),'Dance Cup competitor flags missing');
assert(dcProjector.includes('multi-country'),'Dance Cup multi-country renderer missing');

const judgeForm=read('admin/judges/edit.php');
assert(judgeForm.includes('$countryIndex<=5'),'judge Flag 1–5 controls missing');
assert(judgeForm.includes('countries_json=:countries_json'),'judge country set save missing');

const sharedForm=read('public/js/bdc-global-branding.js');
assert(sharedForm.includes("index<=5"),'competitor/WDC Flag 1–5 controls missing');
assert(sharedForm.includes("country-set.php?entity="),'stored country-set loading missing');
assert(read('admin/competitors/edit.php').includes("CountrySetService::fromRequest($_POST)"),'shared BDC/SDC country save missing');
assert(read('admin/dance-cup/competitor-edit.php').includes('countries_json=:countries_json'),'WDC country save missing');
const profileApi=read('app/Services/ProfileIntegrationService.php');
assert(profileApi.match(/\$p\['countries'\]/g)?.length>=3,'competitor, judge and WDC API countries arrays missing');

console.log('multi-country flag parity checks passed');
