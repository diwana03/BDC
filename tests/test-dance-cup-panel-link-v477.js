const fs=require('fs'),assert=require('assert');
const setup=fs.readFileSync('admin/dance-cup/automatic-setup.php','utf8');
const view=fs.readFileSync('app/Views/admin/dance-cup-automatic-page.php','utf8');
for(const marker of ['SELECT p.id,p.panel_name','panels.php?panel_id=',"&data_mode=test",'$managedPanelHref'])assert(setup.includes(marker),'missing exact panel routing '+marker);
for(const marker of ['Manage <?=e($managedPanel[\'panel_name\'])?> Panel','Judges, order, Chief Judge and shared links','href="<?=e($managedPanelHref)?>"'])assert(view.includes(marker),'missing actionable panel notice '+marker);
console.log('Dance Cup assigned judging panel link v477 checks passed.');
