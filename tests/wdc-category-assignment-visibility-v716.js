const fs=require('fs');
const source=fs.readFileSync('admin/dance-cup/competitors.php','utf8');
const version=JSON.parse(fs.readFileSync('VERSION.json','utf8'));
const assert=(ok,message)=>{if(!ok)throw new Error(message)};

const liveAssignment="['bdc_dance_cup_entries','bdc_dance_cup_competitions','bdc_dance_cup_events','live']";
const testAssignment="['bdc_test_dance_cup_entries','bdc_test_dance_cup_competitions','bdc_test_dance_cup_events','test']";
assert(source.includes(liveAssignment),'Live Dance Cup category assignments are not merged');
assert(source.includes(testAssignment),'Test Dance Cup category assignments are not merged');
assert(source.includes('SELECT de.wdc_identity_id,de.display_name,dc.dance_style,dc.category_name,dce.name event_name'),'assignment query must read the saved category dance style');
assert(source.includes("if($identity<1)")&&source.includes('$identityNames[$nameKey]??0'),'legacy unlinked roster rows must use only the unique-name identity fallback');
assert(source.includes("$mode==='test'?'TEST · ':'"),'Test category assignments must be visibly distinguished');
assert(source.includes("!in_array($style,$r['dance_styles'],true)"),'style filtering must use explicit assigned styles');
assert(source.includes("implode(' / ',array_map('wdcpLabel',$r['dance_styles']))"),'the profile row must render explicit assigned styles');
assert(!source.includes("str_contains($r['search'],'salsa')"),'style must not be inferred from free text');
assert(version.version==='2.3.6-dev717','release version must be dev716');
assert(version.build===3423,'release build must be 3423');
console.log('dev716 WDC category assignment visibility checks passed');
