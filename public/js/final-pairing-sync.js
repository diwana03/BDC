(function(){
 'use strict';
 const form=document.getElementById('finalPairingForm');if(!form)return;
 const endpoint=form.dataset.pairingStateUrl,status=document.querySelector('[data-pairing-sync-status]');let lastHash='',busy=false;
 const paint=data=>{for(const pair of data.pairs||[]){const select=form.querySelector('[data-final-leader-id="'+pair.leader_entry_id+'"]');if(select)select.value=String(pair.follower_entry_id||0);const cell=form.querySelector('[data-final-pair-status="'+pair.leader_entry_id+'"]');if(cell)cell.textContent=pair.status||'draft';}if(status)status.textContent=(data.pairs||[]).some(pair=>pair.follower_entry_id>0)?'Pairing synchronized with Emcee.':'Waiting for Emcee Random Match…';};
 async function refresh(){if(document.hidden||busy)return;busy=true;try{const response=await fetch(endpoint+(endpoint.includes('?')?'&':'?')+'_='+Date.now(),{cache:'no-store',headers:{Accept:'application/json'}}),data=await response.json();if(data.ok&&data.hash!==lastHash){lastHash=data.hash;paint(data);}}catch(error){if(status)status.textContent='Pairing synchronization temporarily unavailable.';}finally{busy=false;}}
 refresh();setInterval(refresh,1500);document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh()});
})();
