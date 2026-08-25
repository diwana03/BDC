(()=>{
'use strict';
const form=document.querySelector('#dcJudgeScoreForm[data-dc-judge-live]');
if(!form)return;
const inputs=[...form.querySelectorAll('input[type="number"]')];
const progress=document.getElementById('dcJudgeProgress');
const progressBar=document.getElementById('dcJudgeProgressBar');
let timer=0,chain=Promise.resolve(),dirty=false;
const status=document.createElement('div');
status.className='position-fixed bottom-0 end-0 m-3 px-3 py-2 rounded-pill shadow bg-dark text-white';
status.style.zIndex='1080';status.style.display='none';document.body.append(status);
function show(text,tone='dark'){
 status.textContent=text;status.className='position-fixed bottom-0 end-0 m-3 px-3 py-2 rounded-pill shadow text-white bg-'+tone;
 status.style.display='block';clearTimeout(show.timer);show.timer=setTimeout(()=>status.style.display='none',3500);
}
async function send(action,silent=false){
 const data=new FormData(form);data.set('action',action);data.set('ajax','1');
 if(!silent)show(action==='submit'?'Submitting scores…':'Saving draft…','primary');
 const response=await fetch(location.href,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json'},body:data});
 let payload=null;try{payload=await response.json()}catch{throw new Error('The judge scoring server returned an invalid response.')}
 if(!response.ok||!payload.ok)throw new Error(payload.error||'Scores could not be saved.');
 dirty=false;if(progress)progress.textContent=payload.completed+' / '+payload.required;
 if(progressBar)progressBar.style.width=(payload.required?Math.round(payload.completed/payload.required*100):0)+'%';
 show(payload.message||'Scores saved.','success');
 if(payload.locked){
  inputs.forEach(input=>input.disabled=true);
  form.querySelectorAll('button').forEach(button=>button.remove());
  const dock=form.querySelector('.submit-dock');if(dock)dock.innerHTML='<div class="alert alert-success w-100 mb-0 text-center fw-bold">Submitted · scoring locked</div>';
 }
 return payload;
}
function queued(action,silent=false){
 const operation=chain.then(()=>send(action,silent));chain=operation.catch(()=>{});return operation;
}
inputs.forEach(input=>input.addEventListener('input',()=>{
 dirty=true;clearTimeout(timer);timer=setTimeout(()=>queued('save',true).catch(error=>show(error.message,'danger')),900);
}));
form.addEventListener('submit',event=>{
 event.preventDefault();clearTimeout(timer);
 const submitter=event.submitter;const action=submitter?.value==='submit'?'submit':'save';
 if(action==='submit'&&!confirm('Submit and lock all your Dance Cup scores?'))return;
 queued(action,false).catch(error=>show(error.message,'danger'));
});
window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue=''}});
})();