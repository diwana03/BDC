const fs=require('fs');const assert=require('assert');
const redirect=fs.readFileSync('admin/dance-cup/participants.php','utf8');
const source=fs.readFileSync('admin/dance-cup/competitors.php','utf8');
assert(redirect.includes('competitors.php'),'legacy participant route does not redirect');
assert(source.includes('Edit profile')&&source.includes('Adjust photo'),'WDC actions missing');
assert(source.includes('No WDC competitors match'),'safe empty state missing');
console.log('Dance Cup participant consolidation checks passed');
