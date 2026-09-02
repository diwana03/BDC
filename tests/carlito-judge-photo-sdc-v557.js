const fs=require('fs'),assert=require('assert');
const migration=fs.readFileSync('database/migrations/20260902_0300_copy_carlito_judge_photo_to_sdc.php','utf8');
for(const marker of ['SDC-000096',"c.bdc_id IS NULL","ri.council='sdc'","status='active'",'count($matches)!==1','photo_url',"WHERE id=:id AND bdc_id IS NULL"])assert(migration.includes(marker),marker+' safety missing');
assert(!migration.includes('UPDATE bdc_judges'),'judge record must remain unchanged');
assert(!migration.includes("council='bdc'"),'BDC identity must remain untouched');
console.log('Carlito judge photo SDC v557 checks passed');
