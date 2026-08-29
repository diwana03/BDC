const fs = require('fs');
const assert = require('assert');

const projector = fs.readFileSync('admin/dance-cup/projector.php', 'utf8');
const branding = fs.readFileSync('public/js/bdc-global-branding.js', 'utf8');
const flags = fs.readFileSync('app/Services/CountryFlagService.php', 'utf8');

assert(projector.includes("fetch('./projection-feed.php?token='") && projector.includes("credentials:'same-origin'"), 'projector feed must use an explicit same-origin request');
assert(projector.includes('AbortController') && projector.includes('12000'), 'stalled projector feeds must recover on a bounded timeout');
assert(projector.includes('right:clamp(14px,1.8vw,34px)') && !projector.includes('left:50%;bottom:5vh'), 'connection warning must not cover the venue screen');
assert(branding.includes("classList.contains('dc-projector-presentation')"), 'global portal branding must leave Dance Cup projector geometry untouched');
assert(!flags.includes('mb_chr(') && flags.includes('chr(0xF0|($value>>18))'), 'flag rendering must not depend on optional mb_chr support');

console.log('Dance Cup projector recovery v494 checks passed.');
