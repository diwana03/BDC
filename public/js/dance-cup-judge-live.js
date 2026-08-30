(()=>{
'use strict';
const form=document.querySelector('#dcJudgeScoreForm[data-dc-judge-live]');
if(!form)return;
const scoreInputs=[...form.querySelectorAll('input[type="number"]')];
const progress=document.getElementById('dcJudgeProgress');
const progressBar=document.getElementById('dcJudgeProgressBar');
const locked=!form.querySelector('button[value="submit"]');
form.classList.toggle('dc-category-submitted',locked);
const acceptanceKey='bdc-dc-rules-'+(form.querySelector('input[name="token"]')?.value||location.pathname);
let timer=0,chain=Promise.resolve(),dirty=false,accepted=locked;
const status=document.createElement('div');
status.className='dc-judge-notification';status.setAttribute('role','status');status.setAttribute('aria-live','polite');status.style.display='none';document.body.append(status);
function show(text,tone='dark'){
 status.textContent=text;status.className='dc-judge-notification text-white bg-'+tone;
 status.style.display='block';clearTimeout(show.timer);show.timer=setTimeout(()=>status.style.display='none',3500);
}
function escapeAttribute(value){return String(value).replace(/[&<>"']/g,character=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[character]));}
function format(value){const number=Number(value);return Number.isInteger(number)?String(number):number.toFixed(1).replace(/\.0$/,'');}
function clientProgress(){
 const completed=scoreInputs.filter(input=>input.value!=='').length,required=scoreInputs.length;
 if(progress)progress.textContent=completed+' / '+required;
 if(progressBar)progressBar.style.width=(required?Math.round(completed/required*100):0)+'%';
}
function updateEntryTotal(article){
 const values=[...article.querySelectorAll('.dc-score-value')],scored=values.filter(input=>input.value!=='');
 const total=scored.reduce((sum,input)=>sum+Number(input.value||0),0),maximum=values.reduce((sum,input)=>sum+Number(input.dataset.maximum||0),0);
 const target=article.querySelector('[data-entry-live-total]');
 if(target)target.textContent=(scored.length?format(total):'—')+' / '+format(maximum);
}
function updateSliderVisual(slider){const maximum=Number(slider.max||100),value=Number(slider.value||0);slider.style.setProperty('--dc-score-progress',(maximum?value/maximum*100:0)+'%');}
function refreshCompletion(){
 const completed=scoreInputs.filter(input=>input.value!=='').length,required=scoreInputs.length;
 document.querySelectorAll('.entry-card').forEach(article=>{const inputs=[...article.querySelectorAll('.dc-score-value')],done=inputs.filter(input=>input.value!=='').length,badge=article.querySelector('[data-entry-completion]');if(badge)badge.textContent=done+' / '+inputs.length;article.classList.toggle('dc-entry-complete',done===inputs.length);article.classList.toggle('dc-entry-incomplete',done!==inputs.length)});
 const submit=form.querySelector('button[value="submit"]');if(submit)submit.disabled=!accepted||locked||completed!==required;
 const next=form.querySelector('[data-next-missing]');if(next){next.disabled=!accepted||locked||completed===required;next.textContent=completed===required?'All scores complete ✓':'Next missing score · '+(required-completed)+' left';}
}
function focusNextMissing(){const missing=scoreInputs.find(input=>input.value==='');if(!missing)return;const box=missing.closest('.score-box');box.classList.add('dc-score-attention');box.scrollIntoView({behavior:'smooth',block:'center'});setTimeout(()=>box.classList.remove('dc-score-attention'),1800);box.querySelector('.dc-score-slider')?.focus();}
function scheduleSave(){dirty=true;clearTimeout(timer);timer=setTimeout(()=>queued('save',true).catch(error=>show(error.message,'danger')),900);}
function showAutosaveProtection(){const dock=form.querySelector('.submit-dock');if(!dock||locked)return;const note=document.createElement('div');note.className='small text-center text-muted mb-2';note.setAttribute('data-dc-autosave-state','');note.textContent='Automatic draft saving is on · changes save after every selection';dock.prepend(note);}
function buildSliders(){
 scoreInputs.forEach(input=>{
  const box=input.closest('.score-box'),maximum=Number(input.max||100),value=input.value;
  input.type='hidden';input.classList.add('dc-score-value');input.dataset.maximum=String(maximum);
  const control=document.createElement('div');control.className='dc-score-control';
  control.innerHTML='<div class="dc-score-readout"><span class="dc-score-state">'+(value===''?'Not scored':'Score selected')+'</span><output class="dc-score-output" aria-live="polite">'+(value===''?'—':format(value))+'</output><small>/ '+format(maximum)+'</small></div><input class="dc-score-slider" type="range" min="0" max="'+maximum+'" step="0.1" value="'+(value===''?'0':value)+'" aria-label="'+escapeAttribute(box.querySelector('label')?.textContent.trim()||'Score')+'"><div class="dc-score-scale"><span>0</span><span>'+format(maximum)+'</span></div><button type="button" class="dc-score-zero">Set intentional 0</button>';
  input.after(control);const slider=control.querySelector('.dc-score-slider'),output=control.querySelector('.dc-score-output');
  const stateLabel=control.querySelector('.dc-score-state'),zero=control.querySelector('.dc-score-zero');slider.disabled=!accepted||locked;zero.disabled=!accepted||locked;box.classList.toggle('is-scored',value!=='');updateSliderVisual(slider);
  const selectValue=raw=>{slider.value=String(raw);input.value=slider.value;output.textContent=format(slider.value);stateLabel.textContent='Score selected';box.classList.add('is-scored');updateSliderVisual(slider);updateEntryTotal(box.closest('.entry-card'));clientProgress();refreshCompletion();show('Selected '+format(slider.value)+' / '+format(maximum),'primary');scheduleSave();};
  slider.addEventListener('input',()=>selectValue(slider.value));zero.addEventListener('click',()=>selectValue(0));
 });
 document.querySelectorAll('.entry-card').forEach(article=>{const heading=article.querySelector('.d-flex.align-items-baseline');if(heading){const completion=document.createElement('span');completion.className='dc-entry-completion ms-auto';completion.innerHTML='<strong data-entry-completion>0 / 0</strong><small>criteria scored</small>';heading.appendChild(completion);const badge=document.createElement('span');badge.className='dc-entry-live-total';badge.innerHTML='<small>Live total</small><strong data-entry-live-total>—</strong>';heading.appendChild(badge);}updateEntryTotal(article);});
 const dock=form.querySelector('.submit-dock .d-flex');if(dock&&!locked){const next=document.createElement('button');next.type='button';next.className='btn btn-dark btn-lg dc-next-missing';next.setAttribute('data-next-missing','');next.addEventListener('click',focusNextMissing);dock.prepend(next);}
 clientProgress();refreshCompletion();
}
function setScoringEnabled(enabled){accepted=enabled;form.querySelectorAll('.dc-score-slider,.dc-score-zero').forEach(input=>input.disabled=!enabled||locked);form.querySelectorAll('button').forEach(button=>button.disabled=!enabled||locked);refreshCompletion();}
function buildRules(){
 if(locked)return;
 const panel=document.createElement('section');panel.className='card border-warning shadow-sm mb-4 dc-judge-rules';panel.innerHTML='<div class="card-body"><span class="badge text-bg-warning">REQUIRED BEFORE SCORING</span><h2 class="h4 mt-2">Judge Scoring Rules</h2><p class="mb-2">Please read and accept before entering any marks.</p><ul><li>Score every contestant independently and only within each displayed maximum.</li><li>Do not discuss or compare marks with another judge while scoring is open.</li><li>Review every selected value before submission.</li><li>Submit Scores is final and locks this judge sheet until the scorer reopens it.</li></ul><label class="dc-rule-accept"><input type="checkbox" class="form-check-input" id="dcJudgeRuleCheck"> I have read, understood and agree to follow these scoring rules.</label><button type="button" class="btn btn-warning btn-lg mt-3" id="dcJudgeRuleAccept" disabled>Accept Rules &amp; Start Scoring</button></div>';
 form.before(panel);const check=panel.querySelector('#dcJudgeRuleCheck'),button=panel.querySelector('#dcJudgeRuleAccept');
 check.addEventListener('change',()=>button.disabled=!check.checked);
 button.addEventListener('click',()=>{try{sessionStorage.setItem(acceptanceKey,'1')}catch{}setScoringEnabled(true);panel.classList.add('rules-accepted');panel.innerHTML='<div class="card-body py-3 d-flex align-items-center gap-2"><span class="badge text-bg-success">Accepted</span><strong>Judge rules accepted. Scoring is open.</strong></div>';show('Rules accepted. You may begin scoring.','success');form.querySelector('.dc-score-slider')?.focus();});
 try{if(sessionStorage.getItem(acceptanceKey)==='1'){accepted=true;panel.remove();}}catch{}
}
async function send(action,silent=false){
 if(!accepted)throw new Error('Accept the Judge Scoring Rules before scoring.');
 const data=new FormData(form);data.set('action',action);data.set('ajax','1');
 if(!silent)show(action==='submit'?'Submitting scores…':'Saving draft…','primary');
 const response=await fetch(location.href,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json'},body:data});
 let payload=null;try{payload=await response.json()}catch{throw new Error('The judge scoring server returned an invalid response.')}
 if(!response.ok||!payload.ok)throw new Error(payload.error||'Scores could not be saved.');
 dirty=false;if(progress)progress.textContent=payload.completed+' / '+payload.required;
 if(progressBar)progressBar.style.width=(payload.required?Math.round(payload.completed/payload.required*100):0)+'%';
 show(payload.message||'Scores saved.','success');
 form.dispatchEvent(new CustomEvent('dc:judge-saved',{detail:{action,payload}}));
 if(payload.locked){form.classList.add('dc-category-submitted');form.querySelectorAll('.dc-score-slider,.dc-score-zero').forEach(input=>input.disabled=true);form.querySelectorAll('.submit-dock button').forEach(button=>button.remove());const dock=form.querySelector('.submit-dock');if(dock)dock.innerHTML='<div class="alert alert-success w-100 mb-0 text-center fw-bold">Submitted · all contestant scores locked</div>';}
 return payload;
}
function queued(action,silent=false){const operation=chain.then(()=>send(action,silent));chain=operation.catch(()=>{});return operation;}
buildRules();buildSliders();showAutosaveProtection();setScoringEnabled(accepted);
form.addEventListener('submit',event=>{event.preventDefault();clearTimeout(timer);const action=event.submitter?.value==='submit'?'submit':'save';if(action==='submit'&&scoreInputs.some(input=>input.value==='')){show('Complete every highlighted score before submitting.','danger');focusNextMissing();return;}if(action==='submit'&&!confirm('All '+scoreInputs.length+' scores are complete. Submit and lock this judge sheet?'))return;queued(action,false).catch(error=>show(error.message,'danger'));});
window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue=''}});
})();
