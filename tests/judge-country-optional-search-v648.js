const fs=require('node:fs');
const assert=require('node:assert/strict');

const directory=fs.readFileSync('app/Services/JudgeDirectoryService.php','utf8');
const adminSearch=fs.readFileSync('admin/judges/search.php','utf8');
const scoringSearch=fs.readFileSync('admin/scoring/judge-directory-search.php','utf8');
const automaticSetup=fs.readFileSync('admin/scoring/automatic-common-setup.php','utf8');

assert.ok(directory.includes("FROM bdc_judges WHERE status='active' AND (LOWER(full_name) LIKE LOWER(:full_contains)"),'active Judge Database lookup must begin from name matching, not country');
assert.ok(!/WHERE\s+status='active'[^\n]*country\s+(?:IS NOT NULL|<>|=)/i.test(directory),'Judge Database lookup must not require country');
assert.ok(directory.includes("COALESCE(display_name,'')")&&directory.includes("COALESCE(judge_code,'')"),'optional Judge Database fields must be null-safe');

assert.ok(adminSearch.includes("$country=trim((string)($r['country']??''))"),'admin judge search must normalize missing country to blank');
assert.ok(adminSearch.includes("$countryCode=trim((string)($r['country_code']??''))"),'admin judge search must normalize missing country code to blank');
assert.ok(adminSearch.includes("$r['flag']=CountryFlagService::emoji($countryCode!==''?$countryCode:($country!==''?$country:null))"),'admin judge search must emit an empty flag safely when country is absent');
assert.ok(adminSearch.includes('JSON_THROW_ON_ERROR'),'judge search JSON must fail explicitly instead of returning malformed autocomplete data');

assert.ok(scoringSearch.includes("(string)($row['country']??'')"),'shared Test/Live scoring judge search must tolerate missing country');
assert.ok(automaticSetup.includes("array_filter([$name,$code,$country])"),'automatic Test/Live judge suggestions must omit a blank country without omitting the judge');
assert.ok(automaticSetup.includes('data-judge-id='),'automatic Test/Live suggestions must preserve canonical Judge Database ID selection');

console.log('Country-optional Judge Database search v648 regression passed.');
