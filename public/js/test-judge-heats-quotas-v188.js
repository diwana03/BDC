document.addEventListener('DOMContentLoaded',()=>{
 const cards=[...document.querySelectorAll('.score-row')];
 if(!cards.length)return;
 const criteria=document.createElement('button');criteria.type='button';criteria.className='btn btn-outline-primary mb-3';criteria.textContent='View Criteria';criteria.onclick=()=>alert('JUDGING CRITERIA\n\nTiming 20%\nTechnique 20%\nConnection 20%\nMusicality 20%\nPresentation 10%\nDifficulty 10%\n\nHeats: use the configured YES quota and assign A1, A2 and A3 no more than once per role. Comments are optional and private to this judge/device.\n\nJudge independently and keep all scoring confidential.');document.querySelector('.counter')?.before(criteria);
 const limit=Number((document.querySelector('.counter')?.textContent.match(/YES\s+\d+\/(\d+)/i)||[])[1]||10);
 const roles=['leader','follower'];
 const state={leader:{yes:0,alt1:0,alt2:0,alt3:0},follower:{yes:0,alt1:0,alt2:0,alt3:0}};
 const roleLabel=role=>role==='leader'?'Leaders':'Followers';
 const valueKey=value=>value==='yes'?'yes':(/^alt[123]$/.test(value)?value:'');
 const roleFor=card=>card.querySelector('.small')?.textContent.trim().toLowerCase().startsWith('follow')?'follower':'leader';
 cards.forEach(card=>{
  card.dataset.quotaRole=roleFor(card);
  card.dataset.currentMark=card.querySelector('input:checked')?.value||'no';
  const key=valueKey(card.dataset.currentMark);if(key)state[card.dataset.quotaRole][key]++;
 });
 const counters=[...document.querySelectorAll('.counter')];
 counters.forEach(counter=>{counter.dataset.quotaCounter=counter.textContent.trim().toLowerCase().startsWith('followers')?'follower':'leader';});
 let message=document.getElementById('quotaRuleMessage');
 if(!message){message=document.createElement('div');message.id='quotaRuleMessage';message.className='alert alert-warning';message.style.display='none';document.querySelector('.counter')?.before(message);}
 const notify=text=>{message.textContent=text;message.style.display='block';message.scrollIntoView({behavior:'smooth',block:'nearest'});clearTimeout(notify.timer);notify.timer=setTimeout(()=>message.style.display='none',4000);};
 const update=()=>{
  roles.forEach(role=>{
   const current=state[role],counter=document.querySelector(`[data-quota-counter="${role}"]`);
   if(counter){counter.innerHTML=`<strong>${roleLabel(role)}</strong> · YES ${current.yes}/${limit} · A1 ${current.alt1}/1 · A2 ${current.alt2}/1 · A3 ${current.alt3}/1`;counter.classList.toggle('complete',current.yes===limit&&current.alt1===1&&current.alt2===1&&current.alt3===1);}
  });
  cards.forEach(card=>{
   const role=card.dataset.quotaRole,current=card.dataset.currentMark;
   card.querySelectorAll('input').forEach(input=>{
    const key=valueKey(input.value),full=key==='yes'?state[role].yes>=limit:(key?state[role][key]>=1:false);
    input.nextElementSibling?.classList.toggle('quota-unavailable',full&&current!==input.value);
   });
  });
 };
 cards.forEach(card=>card.querySelectorAll('input').forEach(input=>input.addEventListener('change',()=>{
  const role=card.dataset.quotaRole,oldValue=card.dataset.currentMark||'no',newValue=input.value,oldKey=valueKey(oldValue),newKey=valueKey(newValue);
  const full=newKey==='yes'?state[role].yes>=limit:(newKey?state[role][newKey]>=1:false);
  if(newKey&&newValue!==oldValue&&full){
   const oldInput=card.querySelector(`input[value="${oldValue}"]`);if(oldInput)oldInput.checked=true;input.checked=false;
   notify(newKey==='yes'?`Maximum ${limit} YES selections allowed for ${roleLabel(role)}. Change another YES first.`:`${newKey.toUpperCase()} has already been assigned to a ${role==='leader'?'Leader':'Follower'}. Change that selection first.`);
   return;
  }
  if(oldKey)state[role][oldKey]=Math.max(0,state[role][oldKey]-1);
  if(newKey)state[role][newKey]++;
  card.dataset.currentMark=newValue;update();
 })));update();
});
document.addEventListener('DOMContentLoaded',()=>{
 if(!document.body.textContent.includes('Submitted.'))return;
 const endpoint=new URL(location.href);endpoint.searchParams.set('status','1');
 (function poll(){if(document.hidden){setTimeout(poll,10000);return;}endpoint.searchParams.set('_',Date.now());fetch(endpoint,{cache:'no-store'}).then(r=>r.status===429?null:r.json()).then(data=>{if(data&&data.status!=='submitted')location.reload()}).catch(()=>{}).finally(()=>setTimeout(poll,10000));})();
});
