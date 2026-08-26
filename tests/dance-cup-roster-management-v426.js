'use strict';
const fs=require('fs'),assert=require('assert');
const read=file=>fs.readFileSync(file,'utf8');
const service=read('app/Services/DanceCupRosterService.php');
const automatic=read('admin/dance-cup/automatic-setup.php');
const manual=read('admin/dance-cup/category.php');
const api=read('admin/dance-cup/roster-api.php');
const client=read('public/js/dance-cup-judge-order.js');
const workflow=read('admin/dance-cup/workflow.php');
const deletion=read('admin/dance-cup/delete-draft.php');
for(const action of ['remove_competitor','move_competitor','remove_judge','move_judge','set_chief_judge']){
 assert(service.includes("'"+action+"'"),action+' missing from shared roster service');
 assert(automatic.includes('value="'+action+'"')||api.includes(action),action+' missing from Dance Cup UI/API');
}
assert(service.includes('Cannot remove this {$label} after scoring has started'),'scored roster removal must fail closed');
assert(service.includes('Cannot change the Chief Judge after scoring has started'),'Chief Judge changes must fail closed after marks exist');
assert(service.includes('SET is_chief=(id=:judge)'),'Chief selection must leave exactly one Chief Judge');
assert(automatic.includes('$chiefCount!==1'),'Automatic judge links must require exactly one Chief Judge');
assert(automatic.includes('@media(max-width:1199px)')&&automatic.includes('grid-template-columns:1fr'),'Automatic roster panels must stack before laptop overflow');
assert(manual.includes('dance-cup-judge-order.js?v=426')&&client.includes('Contestants & Judge Panel'),'Manual roster manager missing');
assert(api.includes("Submitted competition rosters are locked"),'Manual submitted roster lock missing');
assert(workflow.includes('delete-draft.php?id=')&&workflow.includes("status']==='draft'"),'Draft delete action must only appear for draft categories');
assert(deletion.includes("confirmation']??'')!=='DELETE'")&&deletion.includes("status']!=='draft'"),'Draft deletion must require typed confirmation and draft status');
assert(deletion.includes('if((int)$remaining->fetchColumn()===0'),'empty parent Draft event cleanup missing');
console.log('PASS Dance Cup shared roster management and protected draft deletion v426');
