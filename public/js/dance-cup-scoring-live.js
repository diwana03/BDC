(()=>{
'use strict';
const manual=document.querySelector('[data-dc-manual]');
const automatic=document.querySelector('[data-dc-automatic]');
if(!manual&&!automatic)return;
const root=manual||automatic;
const endpoint=root.dataset.api;
let chain=Promise.resolve(),dirty=false,saveTimer=0;
const escapeHtml=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let notice=document.getElementById('dcLiveNotice');
if(!notice){notice=document.createElement('div');notice.id='dcLiveNotice';notice.className='alert d-none no-print';root.prepend(notice)}
function message(text,type='success'){
 notice.textContent=text;notice.className='alert alert-'+type+' no-print';
 clearTimeout(message.timer);message.timer=setTimeout(()=>notice.classList.add('d-none'),5000);
}
async function rawRequest(data){
 const response=await fetch(endpoint,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json'},body:data});
 let payload=null;try{payload=await response.json()}catch{throw new Error('The server returned an invalid scoring response.')}
 if(!response.ok||!payload.ok)throw new Error(payload.error||'Dance Cup scoring could not be updated.');
 return payload;
}
function request(data){
 const operation=chain.then(()=>rawRequest(data));
 chain=operation.catch(()=>{});
 return operation;
}
function formatScore(value){
 const number=Number(value||0);return Number.isInteger(number)?String(number):number.toFixed(2).replace(/0+$/,'').replace(/\.$/,'');
}
function renderResults(state){
 document.querySelectorAll('[data-dc-results]').forEach(body=>{
  body.innerHTML=state.results.length?state.results.map(row=>'<tr><td><strong>'+escapeHtml(row.placement)+'</strong></td><td>'+escapeHtml(row.bib_number)+'</td><td>'+escapeHtml(row.display_name)+'</td><td>'+escapeHtml(formatScore(row.total_score))+'</td></tr>').join(''):'<tr><td colspan="4" class="text-muted">Save scores, then calculate totals.</td></tr>';
 });
}
function renderState(state){
 renderResults(state);
 document.querySelectorAll('[data-dc-last-updated]').forEach(node=>node.textContent='Updated '+new Date().toLocaleTimeString([], {hour:'2-digit',minute:'2-digit',second:'2-digit'}));
 document.querySelectorAll('[data-dc-matrix-mark]').forEach(cell=>{const value=state.mark_matrix?.[cell.dataset.entry]?.[cell.dataset.judge]?.[cell.dataset.criterion];cell.innerHTML=value===undefined?'<span class="text-muted">—</span>':escapeHtml(formatScore(value));});
 document.querySelectorAll('[data-dc-matrix-total]').forEach(cell=>{const value=state.row_totals?.[cell.dataset.entry]?.[cell.dataset.judge];cell.innerHTML=value===undefined?'<span class="text-muted">—</span>':escapeHtml(formatScore(value));});
 document.querySelectorAll('[data-dc-matrix-place]').forEach(cell=>{const row=state.results.find(item=>Number(item.entry_id)===Number(cell.dataset.entry));cell.innerHTML=row?'<strong>#'+escapeHtml(row.placement)+'</strong>':'<span class="text-muted">Not calculated</span>';});
 document.querySelectorAll('[data-dc-status]').forEach(node=>{
  node.textContent=String(state.competition_status||'draft').toUpperCase();
  node.classList.toggle('text-bg-success',state.competition_status==='submitted');
  node.classList.toggle('text-bg-secondary',state.competition_status!=='submitted');
 });
 document.querySelectorAll('[data-dc-round-summary]').forEach(node=>{
  node.textContent=state.mark_count+' / '+state.required_marks+' marks · '+state.completed_judges+' / '+state.judge_count+' judges complete';
 });
 document.querySelectorAll('[data-session-id]').forEach(card=>{
  const session=state.sessions.find(item=>Number(item.id)===Number(card.dataset.sessionId));if(!session)return;
  const done=Number(session.completed_count||0),required=Number(session.required_count||0),percent=required?Math.round(done/required*100):0;
  const count=card.querySelector('[data-session-count]');if(count)count.textContent=done+' / '+required+' marks';
  const bar=card.querySelector('[data-session-progress]');if(bar)bar.style.width=percent+'%';
  const complete=required>0&&done>=required,status=session.status==='submitted'?'Submitted':complete?'Complete':session.status==='scoring'?'Scoring':'Not Started';
  const badge=card.querySelector('[data-session-state]');if(badge){badge.textContent=status;badge.className='badge text-bg-'+(status==='Submitted'?'success':status==='Complete'?'primary':status==='Scoring'?'warning':'secondary');}
  card.classList.toggle('submitted',session.status==='submitted');card.classList.toggle('scoring',session.status==='scoring');
 });
 const submitted=state.competition_status==='submitted';
 document.querySelectorAll('[data-dc-lock-on-submit]').forEach(button=>button.disabled=submitted);
 const finalButton=document.querySelector('[data-dc-api-action="submit"]');
 if(finalButton)finalButton.disabled=submitted||!state.all_marks_complete||!state.results_current;
 const calculateButton=document.querySelector('[data-dc-api-action="calculate"]');
 if(calculateButton)calculateButton.disabled=submitted||Number(state.mark_count)<1;
}
if(manual){
 const scoreForm=[...manual.querySelectorAll('form')].find(form=>form.querySelector('input[name="action"][value="save_scores"]'));
 const updateRow=row=>{const total=[...row.querySelectorAll('.score-input')].reduce((sum,input)=>sum+(input.value===''?0:Number(input.value)||0),0);const output=row.querySelector('td:last-child strong');if(output)output.textContent=formatScore(total)};
 scoreForm?.querySelectorAll('tr').forEach(updateRow);
 const save=async(silent=false)=>{
  if(!scoreForm)return null;
  const data=new FormData(scoreForm);data.set('action','save_scores');data.set('ajax','1');
  if(!silent)message('Saving score draft…','info');
  const payload=await request(data);dirty=false;renderState(payload.state);message(payload.message,'success');return payload;
 };
 scoreForm?.addEventListener('input',event=>{
  if(event.target.matches('.score-input')){updateRow(event.target.closest('tr'));dirty=true;clearTimeout(saveTimer);saveTimer=setTimeout(()=>save(true).catch(error=>message(error.message,'danger')),900);}
 });
 scoreForm?.addEventListener('submit',event=>{event.preventDefault();clearTimeout(saveTimer);save(false).catch(error=>message(error.message,'danger'))});
 manual.querySelectorAll('form').forEach(form=>{
  const action=form.querySelector('input[name="action"]')?.value;
  if(!['calculate','checkpoint','submit'].includes(action))return;
  form.addEventListener('submit',async event=>{
   event.preventDefault();
   if(action==='submit'&&!confirm('Submit and lock this competition?'))return;
   try{
    clearTimeout(saveTimer);
    if(dirty)await save(true);
    const data=new FormData(form);data.set('action',action);data.set('ajax','1');data.set('workflow','manual');
    message(action==='calculate'?'Calculating current totals…':'Updating competition…','info');
    const payload=await request(data);renderState(payload.state);message(payload.message,'success');
   }catch(error){message(error.message,'danger')}
  });
 });
}
if(automatic){
 const csrf=automatic.dataset.csrf;
 const postAction=async(action)=>{
  const data=new FormData();data.set('_csrf',csrf);data.set('id',automatic.dataset.competition);data.set('data_mode',automatic.dataset.mode);data.set('action',action);data.set('workflow','automatic');
  const payload=await request(data);renderState(payload.state);message(payload.message,'success');
 };
 automatic.querySelectorAll('[data-dc-api-action]').forEach(button=>button.addEventListener('click',()=>{
  const action=button.dataset.dcApiAction;
  if(action==='submit'&&!confirm('Submit and lock this Dance Cup competition?'))return;
  button.disabled=true;postAction(action).catch(error=>message(error.message,'danger')).finally(()=>{if(action!=='submit')button.disabled=false});
 }));
 const poll=async()=>{
  if(document.hidden)return;
  try{
   const response=await fetch(endpoint,{credentials:'same-origin',headers:{Accept:'application/json'}});
   const payload=await response.json();if(response.ok&&payload.ok)renderState(payload.state);
  }catch{}
 };
 automatic.querySelector('[data-dc-refresh-status]')?.addEventListener('click',event=>{event.currentTarget.disabled=true;poll().finally(()=>event.currentTarget.disabled=false)});
 poll();setInterval(poll,2000);
}
})();
