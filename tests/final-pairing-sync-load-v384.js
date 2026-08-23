'use strict';
const fs=require('fs'),assert=require('assert');
const marker='<script src="../../public/js/bdc-copy-link-v345.js?v=345"></script>';
for(const path of ['admin/scoring-tests/index.php','admin/scoring/core.php']){
 const source=fs.readFileSync(path,'utf8');
 const footer=source.slice(source.lastIndexOf(marker)-100);
 assert(footer.includes('final-pairing-sync.js?v=384'),path+' actual dashboard footer must load pairing sync');
 assert(source.lastIndexOf('final-pairing-sync.js?v=384')<source.lastIndexOf('</body>'),path+' sync include must be inside body');
}
const sync=fs.readFileSync('public/js/final-pairing-sync.js','utf8');
assert(sync.includes('setInterval(refresh,1500)'));
assert(sync.includes("Pairing synchronized with Emcee. Review and confirm these couples."));
assert(sync.includes('save.disabled=!complete'));
assert(sync.includes('confirmButton.disabled=!complete'));
console.log('Final pairing live-refresh asset load parity passed.');
