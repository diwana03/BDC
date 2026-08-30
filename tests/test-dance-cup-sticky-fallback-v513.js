const fs=require('fs'),assert=require('assert');
const judge=fs.readFileSync('admin/dance-cup/judge-scoring.php','utf8');
const stepper=fs.readFileSync('public/js/dance-cup-judge-stepper-v506.js','utf8');
const css=fs.readFileSync('public/css/dance-cup-judge-stepper-v506.css','utf8');
for(const marker of ['dc-competitor-stepper-spacer','updatePinnedStepper','dc-is-fixed','getBoundingClientRect','addEventListener(\'scroll\'','addEventListener(\'resize\''])assert(stepper.includes(marker),'missing robust sticky fallback: '+marker);
for(const marker of ['.dc-competitor-stepper-spacer{height:0}','.dc-competitor-stepper.dc-is-fixed{position:fixed','z-index:1040','.dc-competitor-stepper,.dc-competitor-stepper.dc-is-fixed{top:6px}'])assert(css.includes(marker),'missing fixed fallback presentation: '+marker);
assert(/dance-cup-judge-stepper-v506\.js\?v=(?:51[3-9]|5[2-9]\d|[6-9]\d\d)/.test(judge),'sticky fallback script must retain a cache version at or beyond dev513');
assert(/dance-cup-judge-stepper-v506\.css\?v=(?:51[3-9]|5[2-9]\d|[6-9]\d\d)/.test(judge),'sticky fallback styles must retain a cache version at or beyond dev513');
console.log('dev513 robust sticky contestant navigator checks passed');
