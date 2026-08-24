const fs=require('fs');
const path=require('path');
const root=path.resolve(__dirname,'..');
const read=file=>fs.readFileSync(path.join(root,file),'utf8');

for(const [surface,file,heading,emceeHeading] of [
  ['Test','admin/scoring-tests/index.php','Test Final Judge Selection','Test Emcee Matching Link'],
  ['Live','admin/scoring/core.php','Final Judge Selection','Emcee Matching Link'],
]){
  const source=read(file);
  const judge=source.indexOf(heading);
  const emcee=source.indexOf(emceeHeading,judge);
  const confirm=source.indexOf('Confirm Final Pairing',emcee);
  if(judge<0||emcee<0||confirm<0||!(judge<emcee&&emcee<confirm))throw new Error(`${surface} manual Final order must be Judges, Emcee Matching, then Confirm Pairing`);
  for(const marker of ['save_final_judges','final_chief_key','+ Add Final Judge'])if(!source.slice(judge,emcee).includes(marker))throw new Error(`${surface} pre-pairing judge setup missing ${marker}`);
  if(!source.includes("($round['scoring_mode']??'manual')==='automated'"))throw new Error(`${surface} Automatic Final workflow guard missing`);
}

console.log('OK: Manual Final judges appear before Emcee matching and pairing; Automatic Final remains gated');
