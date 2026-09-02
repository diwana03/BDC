'use strict';
const fs=require('fs'),assert=require('assert'),read=file=>fs.readFileSync(file,'utf8');
const page=read('admin/competitors/index.php');
const edit=read('admin/competitors/edit.php');
const photo=read('admin/competitors/photo-adjust.php');

for(const marker of ['premium-hero','summary-card','premium-panel','premium-table','scope-note','BACHATA DANCE COUNCIL','SALSA DANCE COUNCIL'])assert(page.includes(marker),'premium BDC/SDC dashboard marker missing: '+marker);
assert(page.includes("$dashboard === 'salsa' ? 'sdc' : 'bdc'")&&page.includes("bdc_result_identities WHERE council='bdc'")&&page.includes('FROM bdc_sdc_competitors WHERE status=\'active\''),'council identity isolation changed');
assert(page.includes('LOWER(c.exact_name) LIKE LOWER(:q_name)')&&page.includes('LOWER(ri.identity_code) LIKE LOWER(:q_identity)'),'case-insensitive name/ID search changed');
assert(page.includes("$dashboard==='salsa'?['salsa_rising'")&&page.includes("$dashboard==='salsa'?['novice','intermediate','advanced','unknown']"),'Salsa filters expose Bachata-only divisions or categories');
assert(page.includes("($dashboard===''&&$danceStyle!=='')"),'fixed dashboard scope incorrectly deactivates the All Participants card');
assert(page.includes('photo-adjust.php?id=<?= (int)$row[\'id\'] ?>&amp;dashboard=<?=e($dashboard)?>'),'photo action loses council dashboard context');
assert(edit.includes('competitor_dashboard_')&&edit.includes('SELECT sdc_id FROM bdc_sdc_competitors')&&edit.includes("council='bdc'")&&edit.includes("label.textContent=profileCouncil+' ID'"),'editor does not preserve and render the correct BDC/SDC identity');
assert(photo.includes('name="dashboard" value="<?=e($dashboard)?>"')&&photo.includes("strtoupper($dashboard==='salsa'?'sdc':'bdc')"),'photo workflow loses or mislabels council context');
console.log('Premium BDC and SDC competitor dashboards v578 checks passed');
