const fs=require('fs');
const path=require('path');
const root=path.resolve(__dirname,'..');
const read=file=>fs.readFileSync(path.join(root,file),'utf8');
const service=read('app/Services/RandomPairingService.php');
const projection=read('admin/live-screen/control.php');
const copy=read('public/js/bdc-copy-link-v345.js');

for(const [surface,file,testMode,heading] of [
  ['Test','admin/scoring-tests/index.php','true','Test Emcee Matching Link'],
  ['Live','admin/scoring/core.php','false','Emcee Matching Link'],
]){
  const source=read(file);
  for(const marker of [
    "action==='generate_emcee_link'",
    `RandomPairingService::generateLink($pdo,$roundId,${testMode},$userId)`,
    `RandomPairingService::activeLink($pdo,$roundId,${testMode})`,
    heading,'Copy Link','Open Emcee Matching','Secure access expires','bdc-copy-link-v345.js?v=345',
  ])if(!source.includes(marker))throw new Error(`${surface} Emcee dashboard workflow missing ${marker}`);
}

for(const [label,source,marker] of [
  ['links expire after twelve hours',service,'INTERVAL 12 HOUR'],
  ['link creation is blocked after scoring starts',service,'Emcee Random Match is locked because Final scoring has started.'],
  ['matching reuses the existing projector',projection,'LiveDisplaySessionService::forEvent'],
  ['projection exposes the matching screen',projection,'Emcee Live Matching'],
  ['copy helper includes clipboard fallback',copy,"document.execCommand('copy')"],
])if(!source.includes(marker))throw new Error(`Emcee workflow missing ${label}`);

console.log('OK: dedicated secure Emcee Matching Link is present with Test/Live/projector parity');
