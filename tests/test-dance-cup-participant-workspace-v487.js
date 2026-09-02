const fs=require('fs'),assert=require('assert');
const redirect=fs.readFileSync('admin/dance-cup/participants.php','utf8');
const page=fs.readFileSync('admin/dance-cup/competitors.php','utf8');
const edit=fs.readFileSync('admin/dance-cup/competitor-edit.php','utf8');
assert(redirect.includes("header('Location: competitors.php'"),'legacy participants page must redirect');
for(const required of ['One permanent WDC identity','bdc_wdc_identities','bdc_wdc_registrations','registered_categories','Adjust photo','Possible duplicates'])assert(page.includes(required),'missing consolidated WDC feature: '+required);
assert(edit.includes('Official history and championship points'),'WDC editor history missing');
console.log('consolidated WDC competitor workspace checks passed');
