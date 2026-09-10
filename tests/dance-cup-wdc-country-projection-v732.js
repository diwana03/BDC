const fs = require('fs');

const feed = fs.readFileSync('admin/dance-cup/projection-feed.php', 'utf8');
const diagnostics = fs.readFileSync('app/Services/ProjectionDiagnosticsService.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

assert(feed.includes('$wdcCountry="COALESCE(NULLIF(w.country,\'\'),c.country)"'), 'projector does not prefer the linked WDC country');
assert(feed.includes('$wdcCountries="CASE WHEN NULLIF(w.country,\'\') IS NOT NULL'), 'projector does not keep WDC multi-country data aligned');
assert(feed.includes('{$wdcCountry} country,{$wdcCountries} countries_json,{$wdcPhoto} photo_url'), 'contestant projection identity query is not WDC-first');
assert(feed.includes('COALESCE({$wdcCountry},\'\')') && feed.includes('COALESCE({$wdcCountries},\'\')'), 'live revision does not include WDC country identity');
assert(feed.includes('$identityRevision') && !feed.includes('$photoRevision'), 'projector refresh remains photo-only');
assert(diagnostics.includes('e.wdc_identity_id,COALESCE(NULLIF(w.country,\'\'),c.country) country'), 'Dance Cup diagnostics do not inspect WDC country');
assert(diagnostics.includes("empty($row['competitor_id'])&&empty($row['wdc_identity_id'])"), 'diagnostics still reject valid WDC-only entries');
assert(version.version.startsWith('2.3.6-dev') && version.build >= 3438, 'release metadata is older than dev732');

console.log('Dance Cup WDC country projection v732 regression checks passed');
