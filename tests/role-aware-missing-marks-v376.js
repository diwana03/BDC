'use strict';
const fs=require('fs');
const assert=require('assert');
const paths={testReport:'admin/scoring-tests/result.php',liveReport:'admin/scoring/result.php',testAutomatic:'admin/scoring-tests/automatic-inline.php',liveAutomatic:'admin/scoring/automatic-round.php',projector:'live-display/feed.php'};
const read=path=>fs.readFileSync(path,'utf8');
for(const [name,path] of Object.entries(paths))assert(fs.existsSync(path),name+' is missing');
for(const name of ['testReport','liveReport']){const source=read(paths[name]);assert(source.includes("if(!$assigned)return 'N/A'"),name+' must reserve N/A for unassigned judges');assert(source.includes("return '—'"),name+' must use a dash for an assigned missing mark');assert(source.includes("['all',$role],true)"),name+' must evaluate the judge role for every report cell');}
for(const name of ['testAutomatic','liveAutomatic']){const source=read(paths[name]);assert(source.includes("return 'N/A'"),name+' must show N/A for an unassigned judge role');assert(source.includes("??'—'"),name+' must show a dash for an assigned missing mark');}
const projector=read(paths.projector);assert(projector.includes('$notApplicable=$judgeScope!=="all"&&$judgeScope!==$role'),'Projector must detect unassigned judge roles');assert(projector.includes('$notApplicable?"N/A"'),'Projector must show N/A only when not applicable');assert(projector.includes('??"—"'),'Projector must show a dash for applicable missing marks');
console.log('Role-aware N/A and missing-mark dash parity passed.');
