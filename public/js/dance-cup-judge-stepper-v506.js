(()=>{
'use strict';
const form=document.querySelector('#dcJudgeScoreForm[data-dc-judge-live]');
if(!form)return;
const entries=[...form.querySelectorAll('.entry-card')];
if(entries.length<1)return;
entries.forEach((entry,index)=>{
 entry.dataset.dcEntry=String(index);
 entry.setAttribute('aria-label','Competitor '+(index+1)+' of '+entries.length);
});
let current=Math.max(0,entries.findIndex(entry=>entry.querySelector('.dc-score-value[value=""]')));
if(current<0)current=0;
let advanceAfterSave=false;
const shell=document.createElement('section');
shell.className='dc-competitor-stepper';
shell.innerHTML='<div class="dc-stepper-heading"><div><small>ONE COMPETITOR AT A TIME</small><h2 data-stepper-position></h2></div><div class="dc-stepper-category-progress" data-stepper-complete></div></div><nav class="dc-competitor-history" aria-label="Contestant scoring history"></nav>';
form.prepend(shell);
const history=shell.querySelector('.dc-competitor-history');
const controls=document.createElement('div');
controls.className='dc-stepper-controls';
controls.innerHTML='<button type="button" class="btn btn-outline-secondary btn-lg" data-stepper-previous>← Previous</button><button type="button" class="btn btn-primary btn-lg" data-stepper-next>Save Competitor &amp; Next →</button>';
const dock=form.querySelector('.submit-dock');
if(dock)dock.before(controls);
const originalSave=form.querySelector('button[name="action"][value="save"]');
const finalSubmit=form.querySelector('button[name="action"][value="submit"]');
if(originalSave){originalSave.classList.add('dc-original-save');originalSave.textContent='Save Current Draft';}
if(finalSubmit){finalSubmit.textContent='Submit Category Scores';}
const complete=entry=>{
 const inputs=[...entry.querySelectorAll('.dc-score-value')];
 return inputs.length>0&&inputs.every(input=>input.value!=='');
};
const labelFor=(entry,index)=>entry.querySelector('.display-6')?.textContent.trim()||'#'+(index+1);
function render(){
 const completed=entries.filter(complete).length;
 entries.forEach((entry,index)=>{
  const active=index===current;
  entry.hidden=!active;
  entry.classList.toggle('dc-stepper-active',active);
 });
 shell.querySelector('[data-stepper-position]').textContent='Competitor '+(current+1)+' of '+entries.length;
 shell.querySelector('[data-stepper-complete]').innerHTML='<strong>'+completed+' / '+entries.length+'</strong><span>competitors completed</span>';
 history.innerHTML='';
 entries.forEach((entry,index)=>{
  const button=document.createElement('button');button.type='button';
  button.className='dc-history-item'+(index===current?' is-current':'')+(complete(entry)?' is-complete':'');
  button.setAttribute('aria-current',index===current?'step':'false');
  button.innerHTML='<span>'+labelFor(entry,index)+'</span><small>'+(index===current?'Current':complete(entry)?'Saved ✓':'Not scored')+'</small>';
  button.addEventListener('click',()=>go(index));history.append(button);
 });
 const previous=controls.querySelector('[data-stepper-previous]');
 previous.disabled=current===0;
 const next=controls.querySelector('[data-stepper-next]');
 const last=current===entries.length-1;
 next.hidden=last;
 next.disabled=!complete(entries[current]);
 next.textContent=complete(entries[current])?'Save Competitor & Next →':'Complete this competitor to continue';
 if(finalSubmit)finalSubmit.disabled=completed!==entries.length;
}
function go(index){
 current=Math.max(0,Math.min(entries.length-1,index));render();
 shell.scrollIntoView({behavior:'smooth',block:'start'});
 setTimeout(()=>entries[current].querySelector('.dc-score-slider:not([disabled])')?.focus(),350);
}
controls.querySelector('[data-stepper-previous]').addEventListener('click',()=>go(current-1));
controls.querySelector('[data-stepper-next]').addEventListener('click',()=>{
 if(!complete(entries[current]))return;
 advanceAfterSave=true;
 if(originalSave)originalSave.click();else go(current+1);
});
form.addEventListener('dc:judge-saved',event=>{
 render();
 if(advanceAfterSave&&event.detail?.action==='save'){advanceAfterSave=false;go(current+1);}
});
form.addEventListener('input',()=>setTimeout(render,0),true);
render();
})();
