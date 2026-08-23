'use strict';
const fs=require('fs'),assert=require('assert');
for(const path of ['admin/scoring-tests/index.php','admin/scoring/core.php']){const s=fs.readFileSync(path,'utf8');assert(s.includes('data-final-leader-id'));assert(s.includes('data-pairing-state-url'));assert(s.includes('final-pairing-sync.js?v=382'));assert(s.includes('Emcee Random Match'));}
const sync=fs.readFileSync('public/js/final-pairing-sync.js','utf8');assert(sync.includes('Pairing synchronized with Emcee.'));assert(sync.includes('setInterval(refresh,1500)'));
const presenter=fs.readFileSync('pairing-presenter/index.php','utf8');assert(presenter.includes("($_GET['format']??'')==='json'"));assert(presenter.includes('data.hash!==hash'));
console.log('Emcee and dashboard pairing synchronization parity passed.');
