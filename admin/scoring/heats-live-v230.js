(() => {
'use strict';
const MAP={'1':10,'YES':10,'Y':10,'A1':4.5,'A2':4.3,'A3':4.2,'':0};
const norm=v=>String(v??'').trim().toUpperCase().replace(/^ALT([123])$/,'A$1');
const val=v=>Object.prototype.hasOwnProperty.call(MAP,norm(v))?MAP[norm(v)]:0;
function inputs(row){return [...row.querySelectorAll('input')].filter(i=>!['hidden','submit','button','checkbox','radio'].includes(i.type)&&((i.name||'').toLowerCase().match(/mark|score|judge/)||i.classList.contains('score-input')));}
function idx(t,n){return [...t.querySelectorAll('thead th')].findIndex(x=>x.textContent.trim().toLowerCase()===n);}
function totalCell(r){const i=idx(r.closest('table'),'total');return i>=0?r.children[i]:null;}
function resultCell(r){const h=[...r.closest('table').querySelectorAll('thead th')];const i=h.findIndex(x=>['result','status'].includes(x.textContent.trim().toLowerCase()));return i>=0?r.children[i]:null;}
function calc(r){const ins=inputs(r);if(!ins.length)return 0;let t=0;ins.forEach(i=>{const n=norm(i.value);if(i.value!==n)i.value=n;t+=val(n);});t=Math.round(t*10)/10;r.dataset.total=t;const c=totalCell(r);if(c)c.textContent=t.toFixed(1);return t;}
function tables(){return [...document.querySelectorAll('table')].filter(t=>{const h=[...t.querySelectorAll('thead th')].map(x=>x.textContent.trim().toLowerCase());return h.includes('total')&&h.some(x=>x==='name'||x==='competitor');});}
function rows(t){return [...t.querySelectorAll('tbody tr')].filter(r=>inputs(r).length);}
function hide(){tables().forEach(t=>rows(t).forEach(r=>{const c=resultCell(r);if(c){if(!c.dataset.server)c.dataset.server=c.textContent.trim();c.textContent='—';}}));}
function validate(){document.querySelectorAll('.is-invalid').forEach(x=>x.classList.remove('is-invalid'));const e=[];tables().forEach(t=>{const rs=rows(t),cols=Math.max(0,...rs.map(r=>inputs(r).length));for(let c=0;c<cols;c++){const u={A1:[],A2:[],A3:[]};rs.forEach(r=>{const i=inputs(r)[c];if(i&&u[norm(i.value)])u[norm(i.value)].push(i);});Object.entries(u).forEach(([k,l])=>{if(l.length>1){l.forEach(i=>i.classList.add('is-invalid'));e.push(`Judge ${c+1} has duplicate ${k}.`);}});}});return [...new Set(e)];}
function calculate(){tables().forEach(t=>rows(t).forEach(calc));}
function review(){calculate();const e=validate();if(e.length){alert(e.join('\n'));return;}tables().forEach(t=>{const b=t.querySelector('tbody');rows(t).sort((a,b)=>(+b.dataset.total)-(+a.dataset.total)).forEach(r=>b.appendChild(r));rows(t).forEach(r=>{const c=resultCell(r);if(c)c.textContent=c.dataset.server||'Review';});});document.getElementById('bdc-v230-review')?.classList.remove('d-none');document.getElementById('bdc-v230-back')?.classList.remove('d-none');}
function back(){hide();document.getElementById('bdc-v230-review')?.classList.add('d-none');}
function controls(){if(document.getElementById('bdc-v230-calc'))return;const s=[...document.querySelectorAll('button,input[type=submit]')].find(x=>(x.textContent||x.value||'').toLowerCase().includes('submit scores'));if(!s)return;const d=document.createElement('div');d.className='card mt-3 mb-3';d.innerHTML='<div class="card-body"><h3 class="h6">Heats Calculation Review</h3><p class="small text-muted">Totals update live. Results stay hidden until review.</p><button type="button" id="bdc-v230-calc" class="btn btn-success">Calculate & Sort Results</button> <button type="button" id="bdc-v230-back" class="btn btn-outline-secondary d-none">Back to Scoring</button><div id="bdc-v230-review" class="alert alert-success mt-3 d-none">Results calculated and sorted. Review before Submit Scores.</div></div>';s.closest('form').insertBefore(d,s.parentElement);document.getElementById('bdc-v230-calc').onclick=review;document.getElementById('bdc-v230-back').onclick=back;}
document.addEventListener('input',e=>{if(!(e.target instanceof HTMLInputElement))return;const r=e.target.closest('tr');if(!r||!inputs(r).includes(e.target))return;e.target.value=norm(e.target.value);calc(r);});
function init(){controls();calculate();hide();}
document.readyState==='loading'?document.addEventListener('DOMContentLoaded',init):init();
})();