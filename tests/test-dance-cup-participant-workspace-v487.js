const fs=require('fs'),assert=require('assert');
const redirect=fs.readFileSync('admin/dance-cup/participants.php','utf8');
const page=fs.readFileSync('admin/dance-cup/competitors.php','utf8');
const edit=fs.readFileSync('admin/dance-cup/competitor-edit.php','utf8');
assert(redirect.includes("header('Location: competitors.php'"),'legacy participants page must redirect');
for(const required of ['Complete WDC identity and registration workspace.','bdc_wdc_identities','bdc_wdc_registrations','registration_count','Adjust photo','missing_photo'])assert(page.includes(required),'missing premium WDC feature: '+required);
assert(edit.includes('Official results and points'),'WDC editor history missing');
console.log('consolidated WDC competitor workspace checks passed');
