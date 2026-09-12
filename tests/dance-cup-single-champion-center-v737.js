const fs=require('fs'),assert=require('assert');
const projector=fs.readFileSync('admin/dance-cup/projector.php','utf8');
const version=JSON.parse(fs.readFileSync('VERSION.json','utf8'));

assert(projector.includes('.podium-card.first{--podium-height:100%;grid-column:2'),'a single Champion must occupy the center podium column');
assert(projector.includes('.podium-card.second{--podium-height:88%;grid-column:1'),'second place must occupy the left podium column');
assert(projector.includes('.podium-card.third{--podium-height:80%;grid-column:3'),'third place must occupy the right podium column');
assert(projector.includes('.podium-card,.podium-card.first,.podium-card.second,.podium-card.third{height:auto;min-height:0;grid-column:1}'),'portrait podium must collapse safely to one column');
assert(projector.includes("classes=['second','first','third']"),'podium result order changed');
assert(version.version==='2.3.6-dev737'&&version.build===3443,'release metadata mismatch');
console.log('Dance Cup single Champion center v737 checks passed');
