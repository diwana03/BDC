document.addEventListener('DOMContentLoaded',()=>{
 document.querySelectorAll('input[type="datetime-local"][name]').forEach(input=>{
  const hidden=document.createElement('input');
  hidden.type='hidden';hidden.name=input.name;hidden.value=input.value;
  const date=document.createElement('input');
  date.type='date';date.className='form-control';date.setAttribute('aria-label','Round date');
  const time=document.createElement('select');
  time.className='form-select';time.setAttribute('aria-label','Round time');
  time.innerHTML='<option value="">Select time</option>';
  for(let minutes=0;minutes<1440;minutes+=15){
   const hour=String(Math.floor(minutes/60)).padStart(2,'0'),minute=String(minutes%60).padStart(2,'0');
   const hour12=Math.floor(minutes/60)%12||12,period=minutes<720?'AM':'PM';
   time.add(new Option(`${hour12}:${minute} ${period}`,`${hour}:${minute}`));
  }
  if(input.value){
   const [savedDate,savedTime]=input.value.split('T'),value=(savedTime||'').slice(0,5);date.value=savedDate||'';
   if(value&&![...time.options].some(option=>option.value===value)){
    const [hour,minute]=value.split(':').map(Number),hour12=hour%12||12,period=hour<12?'AM':'PM';
    time.add(new Option(`${hour12}:${String(minute).padStart(2,'0')} ${period}`,value));
   }
   time.value=value;
  }
  const sync=()=>{hidden.value=date.value&&time.value?`${date.value}T${time.value}`:'';};
  date.addEventListener('change',sync);time.addEventListener('change',sync);
  const group=document.createElement('div');group.className='row g-2';
  const dateCol=document.createElement('div');dateCol.className='col-sm-7';dateCol.append(date);
  const timeCol=document.createElement('div');timeCol.className='col-sm-5';timeCol.append(time);
  group.append(dateCol,timeCol);
  input.replaceWith(hidden,group);
 });
});
