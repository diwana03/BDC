(function(){
 'use strict';
 const form=document.getElementById('finalScoreForm');if(!form||!form.dataset.finalScoreStateUrl)return;
 const endpoint=form.dataset.finalScoreStateUrl,status=document.querySelector('[data-final-score-sync-status]'),frame=document.getElementById('automaticJudgeFrame');
 let lastHash='',lastSessionHash='',busy=false;
 const key=(pair,judge)=>String(pair)+':'+String(judge);
 const paint=data=>{
  const marks=new Map((data.marks||[]).map(mark=>[key(mark.pair_id,mark.judge_id),String(mark.rank)]));
  const sessions=new Map((data.judges||[]).map(judge=>[String(judge.id),judge.status]));
  for(const input of form.querySelectorAll('.final-rank-input')){
   const value=marks.get(key(input.dataset.pairId,input.dataset.judgeId))||'';
   const submitted=sessions.get(String(input.dataset.judgeId))==='submitted';
   if(document.activeElement!==input&&input.dataset.localDirty!=='1'){input.value=value;input.dataset.serverValue=value;}
   input.readOnly=submitted;input.classList.toggle('bg-light',submitted);
   input.title=submitted?'Submitted placement locked. Use the audited RESUBMIT control to reopen this judge.':'';
  }
  for(const judge of data.judges||[]){
   const header=form.querySelector('[data-final-judge-header="'+judge.id+'"]');
   if(header)header.classList.toggle('table-success',judge.status==='submitted');
  }
  const results=new Map((data.results||[]).map(result=>[String(result.pair_id),result]));
  for(const rankCell of form.querySelectorAll('[data-final-rank]')){
   const result=results.get(rankCell.dataset.finalRank);rankCell.textContent=result?String(result.rank):'—';
  }
  for(const resultCell of form.querySelectorAll('[data-final-result]')){
   const result=results.get(resultCell.dataset.finalResult);
   if(!result){resultCell.textContent='Not calculated';continue;}
   resultCell.replaceChildren();
   const title=document.createElement('div');title.className='fw-semibold';title.textContent='Relative Placement Summary';
   const majority=document.createElement('div');majority.textContent='Majority achieved in Top '+result.majority_level;
   const count=document.createElement('div');count.textContent=result.majority_count+' judges formed the majority';
   resultCell.append(title,majority,count);
  }
  const submitted=(data.judges||[]).filter(judge=>judge.status==='submitted').length,total=(data.judges||[]).length;
  if(status){status.className='alert alert-success py-2 mb-3';status.textContent='Live score synchronization active · '+submitted+' of '+total+' judges submitted.';}
  const sessionHash=JSON.stringify((data.judges||[]).map(judge=>[judge.id,judge.status]));
  if(lastSessionHash&&sessionHash!==lastSessionHash&&frame)frame.contentWindow.location.reload();
  lastSessionHash=sessionHash;
 };
 for(const input of form.querySelectorAll('.final-rank-input')){
  input.addEventListener('input',()=>{input.dataset.localDirty='1';});
  input.addEventListener('change',()=>{input.dataset.localDirty='1';});
 }
 async function refresh(){
  if(document.hidden||busy)return;busy=true;
  try{
   const response=await fetch(endpoint+(endpoint.includes('?')?'&':'?')+'_='+Date.now(),{cache:'no-store',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
   const data=await response.json();if(!response.ok||!data.ok)throw new Error(data.error||'Score synchronization failed.');
   if(data.hash!==lastHash){lastHash=data.hash;paint(data);}
  }catch(error){if(status){status.className='alert alert-warning py-2 mb-3';status.textContent='Live score updates temporarily unavailable. Saved scores remain safe; retrying automatically.';}}
  finally{busy=false;}
 }
 refresh();setInterval(refresh,3000);document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh();});
})();
