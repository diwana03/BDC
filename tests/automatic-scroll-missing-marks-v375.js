'use strict';
const fs=require('fs');
const assert=require('assert');
const files={testAuto:'admin/scoring-tests/automatic-inline.php',liveAuto:'admin/scoring/automatic-round.php',testReport:'admin/scoring-tests/result.php',liveReport:'admin/scoring/result.php'};
const read=path=>fs.readFileSync(path,'utf8');
for(const [name,path] of Object.entries(files))assert(fs.existsSync(path),name+' is missing');
for(const [name,path] of [['testAuto',files.testAuto],['liveAuto',files.liveAuto]]){const source=read(path);assert(source.includes("['save_scores','calculate_scores','submit_scores']"),name+' must cover every automatic completion action');assert(source.includes('sessionStorage.setItem'),name+' must remember the scorer position');assert(source.includes('scrollTo'),name+' must restore the scorer position');}
for(const [name,path] of [['testReport',files.testReport],['liveReport',files.liveReport]]){const source=read(path);assert(source.includes("return 'N/A'"),name+' must label missing marks N/A');assert(source.includes("number_format((float)$mark['weighted_score']"),name+' must retain numeric marks including zero');}
console.log('Automatic scroll restoration and missing-mark report regression passed.');
