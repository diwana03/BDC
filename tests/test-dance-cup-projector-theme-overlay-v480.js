const fs=require('fs'),assert=require('assert');
const display=fs.readFileSync('admin/dance-cup/projector.php','utf8');
assert(display.includes('.bdc-theme-control,.bdc-theme-fallback-bar{display:none!important}'),'projector must suppress the portal theme overlay');
assert(display.includes('class="dc-projector-presentation"'),'projector presentation surface marker missing');
assert(display.includes('class="official'),'official live display badge must remain');
assert(display.includes('data.state.theme'),'projector premium background must remain independent');
console.log('Dance Cup projector theme overlay v480 passed.');
