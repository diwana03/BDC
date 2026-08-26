(function(){
 'use strict';
 const style=document.createElement('style');style.textContent='.dc-bib-field{flex:0 0 104px!important;width:104px!important}.dc-bib-field .form-control{width:100%}.dc-directory-field{min-width:0}@media(max-width:520px){.dc-bib-field{flex-basis:88px!important;width:88px!important}}';document.head.appendChild(style);
 function escapeHtml(value){return String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]))}
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
 document.addEventListener('DOMContentLoaded',()=>document.querySelectorAll('[data-directory-type]').forEach(attach));
})();
