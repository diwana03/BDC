document.addEventListener('DOMContentLoaded',()=>{
 document.querySelectorAll('.saved-rounds-table').forEach(table=>{
  const picker=table.closest('.card-body')?.querySelector('.column-picker');
  const toggles=[...(picker?.querySelectorAll('.saved-column-toggle')||[])];
  if(!picker||!toggles.length)return;
  const storageKey=table.dataset.storageKey||'bdcSavedRoundColumns';
  const defaults=['event','round_schedule','dance','division','round','status','actions'];
  const all=['event','event_date','round_schedule','dance','division','round','status','updated','actions'];
  let visible;
  try{visible=JSON.parse(localStorage.getItem(storageKey)||'null')}catch(error){}
  if(!Array.isArray(visible))visible=[...defaults];
  const apply=()=>{
   all.forEach(key=>table.querySelectorAll(`[data-col="${key}"]`).forEach(cell=>cell.hidden=!visible.includes(key)));
   toggles.forEach(toggle=>toggle.checked=visible.includes(toggle.value));
   localStorage.setItem(storageKey,JSON.stringify(visible));
  };
  toggles.forEach(toggle=>toggle.addEventListener('change',()=>{
   visible=toggle.checked?[...new Set([...visible,toggle.value])]:visible.filter(key=>key!==toggle.value);
   apply();
  }));
  picker.querySelector('.saved-columns-all')?.addEventListener('click',()=>{visible=[...all];apply();});
  picker.querySelector('.saved-columns-reset')?.addEventListener('click',()=>{visible=[...defaults];apply();});
  document.addEventListener('click',event=>{if(picker.open&&!picker.contains(event.target))picker.removeAttribute('open');});
  apply();
 });
});
