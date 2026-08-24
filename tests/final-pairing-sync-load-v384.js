'use strict';
const fs=require('fs'),assert=require('assert');
const marker='<script src="../../public/js/bdc-copy-link-v345.js?v=345"></script>';
for(const path of ['admin/scoring-tests/index.php','admin/scoring/core.php']){
 const source=fs.readFileSync(path,'utf8');
 const footer=source.slice(Math.max(0,source.lastIndexOf(marker)-500));
 assert(/final-pairing-sync\.js\?v=(?:38[4-9]|[4-9]\d{2,})/.test(footer),path+' actual dashboard footer must load pairing sync');
 const match=[...source.matchAll(/final-pairing-sync\.js\?v=(?:38[4-9]|[4-9]\d{2,})/g)].at(-1);
 assert(match&&match.index<source.lastIndexOf('</body>'),path+' sync include must be inside body');
}
const sync=fs.readFileSync('public/js/final-pairing-sync.js','utf8');
assert(sync.includes('setInterval(refresh,1500)'));
assert(sync.includes("Pairing synchronized with Emcee. Review and confirm these couples."));
assert(sync.includes('save.disabled=!complete'));
assert(sync.includes('confirmButton.disabled=!complete'));
console.log('Final pairing live-refresh asset load parity passed.');
