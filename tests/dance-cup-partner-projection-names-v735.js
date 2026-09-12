const fs=require('fs'),assert=require('assert');
const feed=fs.readFileSync('admin/dance-cup/projection-feed.php','utf8');
const names=fs.readFileSync('app/Services/ProjectionNameService.php','utf8');
const version=JSON.parse(fs.readFileSync('VERSION.json','utf8'));

assert(feed.includes('c.entry_type,c.competition_level'),'projection feed must load the active category entry type');
assert(feed.includes("in_array($entryType,['couple','duo','pro_am','team'],true)"),'partner and ampersand-named team routing is missing');
assert(feed.includes("elseif($entryType==='solo')"),'solo-only abbreviation routing is missing');
assert(feed.includes("ProjectionNameService::abbreviatePartnerRows($entries,['display_name'])"),'partner entry names are not preserved');
assert(feed.includes("ProjectionNameService::abbreviatePartnerRows($results,['display_name'])"),'partner result names are not preserved');
assert(names.includes('public static function abbreviatePartnerRows'),'partner-name helper is missing');
assert(names.includes("preg_split('/\\s*&\\s*/u', $name)"),'partner names are not split at the ampersand');
assert(names.includes("implode(' & '"),'both abbreviated partner names are not rejoined');
assert(names.includes("if (count($parts) < 2 || in_array('', $parts, true)) continue;"),'malformed partner names must remain intact');
assert(version.version.startsWith('2.3.6-dev')&&version.build>=3441,'release metadata is older than dev735');
console.log('Dance Cup partner projection names v735 checks passed');
