(function(){
  'use strict';
  const cfg=window.BDC_SCORING_TEST_MODE||{};
  const mode=cfg.mode==='automated'?'automated':'manual';
  const roundId=Number(cfg.roundId||0);
  const endpoint=String(cfg.automaticEndpoint||'');
  const actionEndpoint=String(cfg.actionEndpoint||'');
  const csrf=String(cfg.csrf||'');
  const judges=Array.isArray(cfg.judges)?cfg.judges:[];
  const special=cfg.specialCategories||{};
  const schedules=cfg.specialSchedules||{};
  const currentDivision=String(cfg.currentDivision||'');

  function isSpecial(value){return Object.prototype.hasOwnProperty.call(special,String(value||''));}
  function scheduleText(value){const s=schedules[value]||{};return Object.entries(s).map(([rank,points])=>rank+'='+points).join(' · ')+' points';}
  function makePostButton(label,action,className,confirmText,extra){
    const form=document.createElement('form');form.method='post';form.action=actionEndpoint;form.style.display='inline';
    if(confirmText)form.onsubmit=()=>window.confirm(confirmText);
    const fields=Object.assign({_csrf:csrf,round_id:String(roundId),test_mode:mode,action:action},extra||{});
    Object.entries(fields).forEach(([name,value])=>{const input=document.createElement('input');input.type='hidden';input.name=name;input.value=String(value);form.appendChild(input);});
    const button=document.createElement('button');button.type='submit';button.className=className;button.textContent=label;form.appendChild(button);return form;
  }

  const subtitle=[...document.querySelectorAll('.text-muted')].find(el=>el.textContent.includes('Scoring Engine')&&el.textContent.includes('Event Round Workflow'));
  if(subtitle)subtitle.textContent=(mode==='automated'?'Automatic Scoring Test':'Manual Scoring Test')+' · Event Round Workflow';
  const heading=[...document.querySelectorAll('h1')].find(el=>el.textContent.trim().startsWith('Scoring Tests Dashboard'));
  if(heading&&!heading.querySelector('[data-test-mode-badge]')){const badge=document.createElement('span');badge.dataset.testModeBadge='1';badge.className='badge ms-2 '+(mode==='automated'?'text-bg-primary':'text-bg-dark');badge.style.fontSize='.72rem';badge.textContent=mode==='automated'?'AUTOMATIC TEST':'MANUAL TEST';heading.appendChild(badge);}

  document.querySelectorAll('select[name="division"]').forEach(select=>{
    Object.entries(special).forEach(([value,label])=>{if(!select.querySelector('option[value="'+value+'"]')){const option=document.createElement('option');option.value=value;option.textContent=label;select.appendChild(option);}});
    if(currentDivision&&select.closest('form')?.querySelector('input[name="round_id"][value="'+roundId+'"]'))select.value=currentDivision;
    const update=()=>{
      const form=select.closest('form');if(!form)return;
      const tier=form.querySelector('select[name="competition_tier"]');
      if(tier){const holder=tier.closest('[class*="col-"]')||tier.parentElement;if(holder)holder.style.display=isSpecial(select.value)?'none':'';tier.disabled=isSpecial(select.value);}
      let note=form.querySelector('[data-special-category-note]');
      if(isSpecial(select.value)){
        if(!note){note=document.createElement('div');note.dataset.specialCategoryNote='1';note.className='alert alert-info py-2 mt-2 mb-0';form.appendChild(note);}
        note.innerHTML='<strong>'+special[select.value]+' fixed points:</strong> '+scheduleText(select.value)+'. Participant-count tiers do not apply.';
      }else if(note)note.remove();
    };
    select.addEventListener('change',update);update();
  });

  if(roundId&&isSpecial(currentDivision)){
    const tier=document.getElementById('competitionTier');
    if(tier){
      const holder=tier.closest('.col-12')||tier.parentElement;if(holder)holder.style.display='none';
      const form=tier.closest('form');
      if(form&&!form.querySelector('[data-special-settings]')){
        const block=document.createElement('div');block.className='col-12';block.dataset.specialSettings='1';
        block.innerHTML='<div class="alert alert-info mb-3"><strong>'+special[currentDivision]+' fixed points:</strong> '+scheduleText(currentDivision)+'<br><span class="small">Participant-count point tiers are disabled. Heats scoring and callbacks still use the shared scoring engine.</span></div><label class="form-label">YES / Callbacks per role</label><input class="form-control" type="number" min="1" max="100" name="special_yes_count" value="'+Number(cfg.yesCount||10)+'"><div class="form-text">This changes callbacks only, not the fixed points schedule.</div>';
        form.querySelector('.row')?.appendChild(block);
      }
    }
  }

  if(roundId&&!document.querySelector('[data-bdc-delete-all-competitors]')){
    const roleCards=[...document.querySelectorAll('.role-card')];
    if(roleCards.length){const host=document.createElement('div');host.className='mb-2 d-flex justify-content-end';host.dataset.bdcDeleteAllCompetitors='1';host.appendChild(makePostButton('Delete All Competitors','delete_all_entries','btn btn-sm btn-outline-danger','Delete all competitors from this TEST round?'));roleCards[0].parentElement.insertBefore(host,roleCards[0]);}
  }
  if(roundId&&!document.querySelector('[data-bdc-judge-delete-controls]')){
    const wrap=document.getElementById('judgesWrap');
    if(wrap){const host=document.createElement('div');host.className='border-top pt-2 mt-2';host.dataset.bdcJudgeDeleteControls='1';const title=document.createElement('div');title.className='small fw-semibold mb-2';title.textContent='Test Judge Controls';host.appendChild(title);const row=document.createElement('div');row.className='d-flex flex-wrap gap-2 align-items-center';judges.forEach(j=>row.appendChild(makePostButton('Delete '+j.judge_name,'delete_judge','btn btn-sm btn-outline-danger','Delete '+j.judge_name+' and this judge’s test marks?',{judge_id:j.id})));row.appendChild(makePostButton('Delete All Judges','delete_all_judges','btn btn-sm btn-outline-danger','Delete all judges and their test marks?'));row.appendChild(makePostButton('Clear Entire Test Round','clear_round','btn btn-sm btn-danger','Clear judges, competitors, marks and results for this TEST round?'));host.appendChild(row);wrap.parentElement.appendChild(host);}
  }

  if(mode!=='automated'||!roundId||!endpoint)return;
  const scoreForm=document.getElementById('heatsScoreForm');
  if(!scoreForm)return;
  let target=scoreForm.closest('.card')||scoreForm;
  const shell=document.createElement('div');shell.id='bdcAutomaticInlineShell';target.replaceWith(shell);
  async function load(){try{const r=await fetch(endpoint,{headers:{'X-Requested-With':'XMLHttpRequest'},cache:'no-store'});if(!r.ok)throw new Error('Automatic judge panel could not load.');shell.innerHTML=await r.text();shell.querySelectorAll('script').forEach(old=>{const s=document.createElement('script');s.textContent=old.textContent;old.replaceWith(s);});}catch(e){shell.innerHTML='<div class="alert alert-danger"><strong>Judge Live Scoring could not load.</strong><br>'+String(e.message||e)+'</div>';}}
  load();
})();
