const fs=require('fs');
const path=require('path');
const root=path.resolve(__dirname,'..');
const read=file=>fs.readFileSync(path.join(root,file),'utf8');

for(const [surface,file,heading,emceeHeading] of [
  ['Test','admin/scoring-tests/index.php','Test Final Judges','Test Emcee Matching Link'],
  ['Live','admin/scoring/core.php','Final Judges','Emcee Matching Link'],
]){
  const source=read(file);
  const manualGuard="<?php if(($round['scoring_mode']??'manual')==='manual'):?>";
  const judge=source.indexOf(heading,source.indexOf(manualGuard));
  const emcee=source.indexOf(emceeHeading,judge);
  const confirm=source.indexOf('Confirm Final Pairing',emcee);
  if(judge<0||emcee<0||confirm<0||!(judge<emcee&&emcee<confirm))throw new Error(`${surface} manual Final order must be Judges, Emcee Matching, then Confirm Pairing`);
  for(const marker of ['save_final_judges','final_chief_key','+ Add Final Judge'])if(!source.slice(judge,emcee).includes(marker))throw new Error(`${surface} pre-pairing judge setup missing ${marker}`);
  if(!source.includes("<?php if(($round['scoring_mode']??'manual')==='automated'):?>\n <div class=\"card border-0 bg-light mb-3\">"))throw new Error(`${surface} post-pairing judge setup is not reserved for unchanged Automatic Final workflow`);
}

console.log('OK: Manual Final judges appear before Emcee matching and pairing; Automatic Final remains gated');
