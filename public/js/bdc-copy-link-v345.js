(function(){
  'use strict';
  if(window.bdcCopyLink)return;
  function fallback(value){
    const field=document.createElement('textarea');field.value=value;field.setAttribute('readonly','');field.style.position='fixed';field.style.left='-9999px';field.style.top='0';document.body.appendChild(field);field.focus();field.select();field.setSelectionRange(0,field.value.length);let copied=false;try{copied=document.execCommand('copy')}catch(error){copied=false}field.remove();return copied;
  }
  function feedback(button,success){
    if(!button)return;const original=button.dataset.copyLabel||button.textContent;button.dataset.copyLabel=original;button.textContent=success?'Copied':'Select & Copy';button.classList.toggle('copy-success',success);clearTimeout(button._bdcCopyTimer);button._bdcCopyTimer=setTimeout(function(){button.textContent=original;button.classList.remove('copy-success')},1600);
  }
  window.bdcCopyLink=async function(button,target){
    const input=typeof target==='string'?document.getElementById(target):target;const value=String(input?.value||input?.textContent||'').trim();if(!value){feedback(button,false);return false}let copied=false;if(window.isSecureContext&&navigator.clipboard?.writeText){try{await navigator.clipboard.writeText(value);copied=true}catch(error){copied=false}}if(!copied)copied=fallback(value);if(!copied&&input?.select){input.focus();input.select();input.setSelectionRange?.(0,value.length)}feedback(button,copied);return copied;
  };
  document.addEventListener('click',function(event){
    const button=event.target.closest?.('button');if(!button)return;const inline=button.getAttribute('onclick')||'';if(!/navigator\.clipboard\.writeText/.test(inline))return;let input=button.previousElementSibling;if(!(input instanceof HTMLInputElement||input instanceof HTMLTextAreaElement)){const match=inline.match(/getElementById\(['"]([^'"]+)/);input=match?document.getElementById(match[1]):null}if(!input){const literal=inline.match(/writeText\(['"]([^'"]+)['"]\)/);if(literal)input={value:literal[1]}}if(!input)return;event.preventDefault();event.stopImmediatePropagation();window.bdcCopyLink(button,input);
  },true);
})();
