const fs=require('fs');
const service=fs.readFileSync('app/Services/DanceCupScoringService.php','utf8');
const manual=fs.readFileSync('admin/dance-cup/category.php','utf8');
const automatic=fs.readFileSync('admin/dance-cup/automatic-setup.php','utf8');
const assert=(condition,message)=>{if(!condition)throw new Error(message)};

assert(!service.includes('This competitor is not approved for the selected Dance Cup style, format and level.'),'profile approval still blocks roster assignment');
assert(!service.includes('SELECT COUNT(*) FROM bdc_competitor_dance_cup_profiles WHERE competitor_id=:competitor'),'profile table is still used as a roster gate');
for(const marker of ['Competitor profile not found.','This category is Female Only.','This category is Male Only.'])assert(service.includes(marker),'required roster protection missing '+marker);
assert(manual.includes('assertDanceCupEligibility')&&automatic.includes('assertDanceCupEligibility'),'Manual and Automatic must retain shared active-profile and event-gender validation');
assert(automatic.includes('already assigned to this category')&&manual.includes('already in this category'),'duplicate roster protection missing');
console.log('Dance Cup reusable profile approval is not a scoring gate v453 checks passed');
