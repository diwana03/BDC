const fs=require('fs'),assert=require('assert');
const setup=fs.readFileSync('admin/dance-cup/automatic-setup.php','utf8');
const css=fs.readFileSync('public/css/scoring-premium.css','utf8');
assert(setup.includes('scoring-premium.css?v=458'),'Automatic workspace must load the dev458 visual layer');
for(const marker of ['premium Automatic Dance Cup control room','body.dc-auto-setup','main>section:first-of-type','#projection-controls','.dc-auto-directory-grid','.dc-workflow-steps','#judge-progress','#round-controls'])assert(css.includes(marker),'missing premium workspace style '+marker);
for(const marker of ['@media(max-width:991.98px)','@media(max-width:575.98px)','scroll-snap-type:x mandatory','grid-template-columns:1fr 1fr'])assert(css.includes(marker),'missing responsive workspace behavior '+marker);
assert(!css.includes('display:none!important}/* dev458'),'premium layer must not hide workflow content');
console.log('dev458 premium Automatic workspace checks passed');
