const fs=require('fs');
const path=require('path');
const presenter=fs.readFileSync(path.resolve(__dirname,'../pairing-presenter/index.php'),'utf8');

for(const marker of ["'countdown'",'let seconds=5','id="autoReveal"',"'screen_type'=>'holding'","'screen_type'=>'matching'"]){
  if(!presenter.includes(marker))throw new Error(`Emcee countdown workflow missing ${marker}`);
}
if(presenter.includes("'champion_impact'"))throw new Error('Emcee reveal must not trigger champion impact automatically');
const revealStart=presenter.indexOf("elseif($action==='reveal')");
const manualEffectStart=presenter.indexOf('elseif(in_array($action',revealStart);
const revealBlock=presenter.slice(revealStart,manualEffectStart);
if(!revealBlock.includes("'none'"))throw new Error('Emcee reveal must clear the countdown overlay');
for(const effect of ['fireworks','confetti','hearts','balloons','heart_smiles','finger_hearts'])if(revealBlock.includes(`'${effect}'`))throw new Error(`Emcee reveal must not trigger ${effect}`);
for(const action of ['hearts','balloons','heart_smiles','finger_hearts'])if(!presenter.includes(`'${action}'=>`))throw new Error(`${action} must remain a manual Emcee action`);
for(const removed of ['value="fireworks"','value="confetti"'])if(presenter.includes(removed))throw new Error(`${removed} must be removed from Emcee Random Match`);

console.log('OK: Emcee Random Match keeps countdown and has no automatic post-countdown effects');
