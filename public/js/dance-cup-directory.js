(function(){
 'use strict';
 const style=document.createElement('style');
 style.textContent=`
 .dc-bib-field{flex:0 0 104px!important;width:104px!important}
 .dc-bib-field .form-control{width:100%}
 .dc-directory-field{min-width:0;position:relative}
 .dc-directory-menu{position:absolute;left:0;right:0;top:calc(100% + 6px);z-index:1080;background:#fff;border:1px solid rgba(15,23,42,.14);border-radius:12px;box-shadow:0 14px 34px rgba(15,23,42,.16);padding:6px;max-height:260px;overflow-y:auto;overscroll-behavior:contain}
 .dc-directory-menu[hidden]{display:none!important}
 .dc-directory-menu button{display:flex;width:100%;flex-direction:column;align-items:flex-start;gap:2px;border:0;background:transparent;text-align:left;padding:9px 11px;border-radius:8px;color:#172033;line-height:1.25}
 .dc-directory-menu button+button{margin-top:2px}
 .dc-directory-menu button:hover,.dc-directory-menu button:focus{background:#eef6ff;outline:none}
 .dc-directory-menu button strong{display:block;width:100%;font-size:.92rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
 .dc-directory-menu button small{display:block;width:100%;font-size:.75rem;color:#6b7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
 .dc-directory-empty{padding:10px 11px;color:#6b7280;font-size:.82rem}
 .dc-directory-selected{border-color:#198754!important;box-shadow:0 0 0 .15rem rgba(25,135,84,.12)!important}
 .dc-directory-hint{display:block;margin-top:4px;font-size:.72rem;color:#6b7280}
 .dc-projection-action{white-space:nowrap}
 @media(max-width:520px){.dc-bib-field{flex-basis:88px!important;width:88px!important}.dc-directory-menu{max-height:220px}.dc-projection-action{width:100%}}
 `;
 document.head.appendChild(style);

 function escapeHtml(value){return String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot',"'":'&#39;'}[char]))}

 function attach(input){
  const type=input.dataset.directoryType,hidden=document.getElementById(input.dataset.directoryTarget),wrap=input.closest('.dc-directory-field');
  if(!type||!hidden||!wrap)return;
  const menu=document.createElement('div');menu.className='dc-directory-menu';menu.hidden=true;wrap.appendChild(menu);let timer,controller;
  const close=()=>{menu.hidden=true;menu.innerHTML=''};
  const choose=item=>{input.value=item.name;hidden.value=item.id;input.classList.add('dc-directory-selected');close();input.focus()};
  const search=()=>{hidden.value='';input.classList.remove('dc-directory-selected');clearTimeout(timer);const q=input.value.trim();if(q.length<1){close();return;}timer=setTimeout(async()=>{controller?.abort();controller=new AbortController();try{const endpoint=new URL('directory-search.php',location.href);endpoint.searchParams.set('type',type);endpoint.searchParams.set('q',q);const response=await fetch(endpoint,{cache:'no-store',headers:{Accept:'application/json'},signal:controller.signal});let data;try{data=await response.json()}catch{throw new Error('invalid-response')}if(!response.ok||!data.ok||!Array.isArray(data.items))throw new Error('search-failed');menu.innerHTML=data.items.length?data.items.map((item,index)=>`<button type="button" data-index="${index}"><strong>${escapeHtml(item.name)}</strong><small>${escapeHtml(item.meta||'BDC Directory')}</small></button>`).join(''):'<div class="dc-directory-empty">No directory match. You may enter a new name.</div>';menu.hidden=false;menu.querySelectorAll('button').forEach(button=>button.onclick=()=>choose(data.items[Number(button.dataset.index)]));}catch(error){if(error.name!=='AbortError'){menu.innerHTML='<div class="dc-directory-empty">BDC Directory search could not load. Please retry.</div>';menu.hidden=false;}}},180)};
  input.addEventListener('input',search);
  input.addEventListener('focus',()=>{if(input.value.trim()&&!hidden.value)search()});
  input.addEventListener('keydown',event=>{const buttons=[...menu.querySelectorAll('button')],active=document.activeElement;if(event.key==='ArrowDown'&&buttons.length){event.preventDefault();(active===input?buttons[0]:buttons[Math.min(buttons.length-1,buttons.indexOf(active)+1)]).focus()}else if(event.key==='ArrowUp'&&buttons.length&&buttons.includes(active)){event.preventDefault();(buttons[buttons.indexOf(active)-1]||input).focus()}else if(event.key==='Escape')close()});
  document.addEventListener('click',event=>{if(!wrap.contains(event.target))close()});
 }

 function addProjectionAction(){
  if(!/\/dance-cup\/automatic-setup\.php$/i.test(location.pathname))return;
  const params=new URLSearchParams(location.search),id=params.get('id');if(!id)return;
  const cards=[...document.querySelectorAll('section.card')];
  const card=cards.find(section=>/Automatic Judge Scoring/i.test(section.textContent||''));if(!card)return;
  const body=card.querySelector('.card-body');if(!body||body.querySelector('.dc-projection-action'))return;
  const link=document.createElement('a');
  const href=new URL('projection-control.php',location.href);href.searchParams.set('id',id);if(params.get('data_mode')==='test')href.searchParams.set('data_mode','test');
  link.href=href.toString();link.className='btn btn-outline-danger btn-lg dc-projection-action';link.textContent='Live Projection';
  const actions=body.querySelector('.btn-lg')?.parentElement;
  if(actions&&actions!==body){actions.classList.add('d-flex','gap-2','flex-wrap');actions.appendChild(link);}else{body.appendChild(link);}
 }

 document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('[data-directory-type]').forEach(attach);
  addProjectionAction();
 });
})();
