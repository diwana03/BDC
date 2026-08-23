'use strict';
const fs=require('fs');
const assert=require('assert');

for(const path of ['admin/scoring-tests/index.php','admin/scoring/core.php']){
 const source=fs.readFileSync(path,'utf8');
 const judgeSetup=source.indexOf('id="finalJudgesForm"');
 const emcee=source.indexOf(path.includes('scoring-tests')?'>Test Emcee Matching Link<':'>Emcee Matching Link<');
 const pairingGate=source.indexOf('if($pairingConfirmed)');
 const judgeLinks=source.indexOf('Automatic Final Judge Scoring');
 assert(judgeSetup>0&&judgeSetup<emcee,path+' must show Final Judge Selection before Emcee Matching');
 assert(source.indexOf('id="finalJudgesForm"',judgeSetup+1)===-1,path+' must not duplicate Final Judge Selection after pairing');
 assert(pairingGate>emcee&&judgeLinks>pairingGate,path+' must keep Automatic judge scoring links behind confirmed pairing');
 assert(source.includes("['manual','automated'],true"),path+' must render pre-matching judge setup for both modes');
}
console.log('Automatic Final judge setup order and pairing gate parity passed.');
