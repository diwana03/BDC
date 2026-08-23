(function(){
 'use strict';
 const style=document.createElement('style');
 style.textContent='.bdc-judge-search{position:relative;min-width:0}.bdc-judge-search-card-open{position:relative!important;z-index:1080!important;overflow:visible!important}.bdc-judge-search-card-open>.card-body{overflow:visible!important}.bdc-judge-search-open{z-index:1090}.input-group>.bdc-judge-search{flex:1 1 auto;width:1%;min-width:0}.input-group>.bdc-judge-search>.form-control{width:100%;min-width:0;border-radius:0}.bdc-judge-menu{position:absolute;top:calc(100% + 4px);left:0;right:0;z-index:1090;max-height:280px;overflow:auto;padding:6px;background:#fff;border:1px solid #cbd5e1;border-radius:10px;box-shadow:0 14px 32px rgba(15,23,42,.18)}.bdc-judge-menu button{display:block;width:100%;border:0;border-radius:7px;background:transparent;padding:9px 10px;text-align:left;color:#172033}.bdc-judge-menu button:hover,.bdc-judge-menu button:focus{background:#edf4ff;outline:0}.bdc-judge-menu small{display:block;color:#64748b}.bdc-judge-empty{padding:10px;color:#64748b;font-size:.875rem}';
 document.head.appendChild(style);
 const escapeHtml=value=>String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
 function attach(input){
  if(input.dataset.bdcJudgeSearch==='1')return;
  input.dataset.bdcJudgeSearch='1';input.removeAttribute('list');
  const row=input.closest('.judge-row')||input.parentElement;
  const hidden=row?.querySelector('input[name="judge_directory_id[]"],input[name$="[directory_id]"]')||null;
  const wrap=document.createElement('div');wrap.className='bdc-judge-search';input.parentNode.insertBefore(wrap,input);wrap.appendChild(input);
  const menu=document.createElement('div');menu.className='bdc-judge-menu';menu.hidden=true;wrap.appendChild(menu);
  const card=wrap.closest('.card');
  let timer,controller;
  const close=()=>{menu.hidden=true;menu.innerHTML='';wrap.classList.remove('bdc-judge-search-open');card?.classList.remove('bdc-judge-search-card-open')};
  const choose=item=>{input.value=item.name;if(hidden)hidden.value=String(item.id);close();input.focus();input.dispatchEvent(new Event('change',{bubbles:true}))};
  const search=()=>{if(hidden)hidden.value='0';clearTimeout(timer);const query=input.value.trim();if(query.length<1){close();return;}timer=setTimeout(async()=>{controller?.abort();controller=new AbortController();try{const endpoint=new URL('../scoring/judge-directory-search.php',location.href);endpoint.searchParams.set('q',query);const response=await fetch(endpoint,{cache:'no-store',headers:{Accept:'application/json'},signal:controller.signal});const data=await response.json();if(!response.ok||!data.ok||!Array.isArray(data.items))throw new Error('search');menu.innerHTML=data.items.length?data.items.map((item,index)=>`<button type="button" data-index="${index}"><strong>${escapeHtml(item.name)}</strong><small>${escapeHtml(item.meta||'Judge Database')}</small></button>`).join(''):'<div class="bdc-judge-empty">No match. Enter a new name if needed.</div>';menu.hidden=false;wrap.classList.add('bdc-judge-search-open');card?.classList.add('bdc-judge-search-card-open');menu.querySelectorAll('button').forEach(button=>button.onclick=()=>choose(data.items[Number(button.dataset.index)]));}catch(error){if(error.name!=='AbortError'){menu.innerHTML='<div class="bdc-judge-empty">Judge Database search could not load.</div>';menu.hidden=false;wrap.classList.add('bdc-judge-search-open');card?.classList.add('bdc-judge-search-card-open');} }},160)};
  input.addEventListener('input',search);input.addEventListener('focus',()=>{if(input.value.trim()&&(!hidden||hidden.value==='0'))search()});
  input.addEventListener('keydown',event=>{const buttons=[...menu.querySelectorAll('button')];if(event.key==='ArrowDown'&&buttons.length){event.preventDefault();buttons[0].focus()}else if(event.key==='Escape')close()});
  document.addEventListener('click',event=>{if(!wrap.contains(event.target))close()});
 }
 const scan=()=>document.querySelectorAll('input[name="judge_name[]"],input[name^="final_judges["][name$="[name]"]').forEach(attach);
 function setupFinalForm(){
  const form=document.getElementById('finalJudgesForm');if(!form||form.dataset.bdcJudgeValidation==='1')return;
  form.dataset.bdcJudgeValidation='1';
  const status=form.querySelector('[data-final-judge-status]');
  const show=(message,type='danger')=>{if(status){status.className='alert alert-'+type+' py-2 mt-2 mb-0';status.textContent=message;status.hidden=false;}};
  form.addEventListener('submit',event=>{
   const rows=[...form.querySelectorAll('[data-judge-row]')];
   const names=rows.map(row=>row.querySelector('input[name^="final_judges["][name$="[name]"]')).filter(Boolean);
   const seenNames=new Map(),seenIds=new Map();let duplicate=null;
   for(const input of names){const normal=input.value.trim().replace(/\s+/g,' ').toLocaleLowerCase();const row=input.closest('[data-judge-row]');const id=row?.querySelector('input[name$="[directory_id]"]')?.value||'0';if(normal&&seenNames.has(normal)){duplicate=input;break;}if(id!=='0'&&seenIds.has(id)){duplicate=input;break;}if(normal)seenNames.set(normal,input);if(id!=='0')seenIds.set(id,input);}
   if(duplicate){event.preventDefault();show('The same judge cannot be selected more than once. Remove the duplicate before saving.');duplicate.focus();duplicate.scrollIntoView({behavior:'smooth',block:'center'});return;}
   if(names.filter(input=>input.value.trim()).length<3){event.preventDefault();show('Minimum 3 Final judges required.');return;}
   if(!form.querySelector('input[name="final_chief_key"]:checked')){event.preventDefault();show('Select one Final Chief Judge before saving.');return;}
   try{sessionStorage.setItem('bdc-final-judge-scroll:'+location.pathname+':'+(form.querySelector('[name="round_id"]')?.value||''),JSON.stringify({y:scrollY,at:Date.now()}));}catch(error){}
   show('Saving Final judges…','info');
  });
  const key='bdc-final-judge-scroll:'+location.pathname+':'+(form.querySelector('[name="round_id"]')?.value||'');let saved=null;try{saved=JSON.parse(sessionStorage.getItem(key)||'null');sessionStorage.removeItem(key);}catch(error){}
  if(saved&&Number.isFinite(saved.y)&&Date.now()-Number(saved.at||0)<120000)requestAnimationFrame(()=>requestAnimationFrame(()=>scrollTo({top:saved.y,behavior:'auto'})));
 }
 document.addEventListener('DOMContentLoaded',()=>{scan();setupFinalForm();new MutationObserver(()=>{scan();setupFinalForm()}).observe(document.body,{childList:true,subtree:true})});
})();
