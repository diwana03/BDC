const assert=require('node:assert/strict');
const fs=require('node:fs');
const {execFileSync}=require('node:child_process');

const service=fs.readFileSync('app/Services/CountryFlagService.php','utf8');
const dashboard=fs.readFileSync('admin/competitors/index.php','utf8');
const editor=fs.readFileSync('admin/competitors/edit.php','utf8');
const integration=fs.readFileSync('app/Services/ProfileIntegrationService.php','utf8');
const migration=fs.readFileSync('database/migrations/20260904_0200_normalize_competitor_countries.php','utf8');
const version=JSON.parse(fs.readFileSync('VERSION.json','utf8'));

for(const token of ['canonicalName','CITY_COUNTRIES',"count($codes)>1"])assert.ok(service.includes(token),'country normalizer safeguard missing '+token);
assert.ok(dashboard.includes('CountryFlagService::canonicalName'),'dashboard country filter must use canonical countries');
assert.ok(editor.includes('CountryFlagService::canonicalName'),'admin saves must canonicalize countries');
assert.ok(integration.includes('CountryFlagService::canonicalName'),'integration imports must canonicalize countries');
for(const token of ["'Japan, Tokyo'=>'Japan'","'Korea/Seoul'=>'South Korea'","'Thailand / Bangkok'=>'Thailand'","'Melbourne Australia'=>'Australia'","'USA'=>'United States of America'"]){
  assert.ok(migration.includes(token),'existing country cleanup missing '+token);
}
assert.ok(migration.includes('bdc_country_normalization_archive'),'country cleanup must remain recoverable');
assert.equal(version.version,'2.3.3-dev625');assert.equal(version.build,3331);

const php=[
  "require 'app/Services/CountryFlagService.php';",
  "$c='App\\Services\\CountryFlagService';",
  "$tests=['Japan, Tokyo'=>'Japan','Jakarta, Indonesia'=>'Indonesia','Korea/Seoul'=>'South Korea','Thailand / Bangkok'=>'Thailand','Melbourne Australia'=>'Australia','USA'=>'United States of America','France/china'=>'France/china','Thailand / Philippines'=>'Thailand / Philippines'];",
  "foreach($tests as $raw=>$expected){if($c::canonicalName($raw)!==$expected){fwrite(STDERR,$raw.' mismatch');exit(1);}}"
].join('');
execFileSync('php',['-r',php],{stdio:'inherit'});
console.log('Canonical country normalization v625 checks passed');
