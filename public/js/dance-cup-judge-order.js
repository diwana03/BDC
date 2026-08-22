(function () {
  'use strict';
  const roster = document.querySelector('[data-dc-judge-roster]');
  const scoreTable = Array.from(document.querySelectorAll('table')).find((table) => table.textContent.includes('Judge Total'));
  if (!roster && !scoreTable) return;
  const csrf = document.querySelector('input[name="_csrf"]')?.value;
  const competitionId = document.querySelector('input[name="id"]')?.value;
  if (!csrf || !competitionId) return;
  const seen = new Map();
  roster?.querySelectorAll('[data-judge-id]').forEach((row) => {
    const id = row.dataset.judgeId || '';
    if (id) seen.set(id, {id, name: row.dataset.judgeName || '', chief: row.dataset.chief === '1'});
  });
  scoreTable?.querySelectorAll('tbody tr').forEach((row) => {
    const input = row.querySelector('input[name^="mark["]');
    const match = input?.name.match(/^mark\[\d+\]\[(\d+)\]/);
    const name = row.cells[1]?.textContent.trim() || '';
    if (match && !seen.has(match[1])) seen.set(match[1], {id: match[1], name, chief: name.includes('★')});
  });
  if (seen.size < 2) return;
  const judges = Array.from(seen.values()).sort((a, b) => Number(b.chief) - Number(a.chief));
  const panel = document.createElement('section');
  panel.className = 'card border-warning shadow-sm my-4 no-print';
  panel.innerHTML = '<div class="card-body"><h2 class="h5">Judge Display Order</h2><p class="text-muted small">Chief Judge is pinned first. This order controls scoring, printed sheets and Live Projection.</p><div data-order-list></div><button type="button" class="btn btn-warning mt-2" data-save-order>Save Judge Order</button><span class="ms-2 small" data-status></span></div>';
  if (roster) roster.after(panel);
  else scoreTable?.closest('form')?.before(panel);
  const list = panel.querySelector('[data-order-list]');
  function paint() {
    list.replaceChildren();
    judges.forEach((judge, index) => {
      const row = document.createElement('div');
      row.className = 'd-flex align-items-center gap-2 border rounded p-2 mb-2' + (judge.chief ? ' border-warning bg-warning-subtle' : '');
      row.innerHTML = '<strong class="me-auto"></strong><button type="button" class="btn btn-sm btn-outline-secondary" data-up>↑</button><button type="button" class="btn btn-sm btn-outline-secondary" data-down>↓</button>';
      row.querySelector('strong').textContent = (index + 1) + '. ' + judge.name + (judge.chief ? ' · Chief · Pinned first' : '');
      row.querySelector('[data-up]').disabled = judge.chief || index <= 1;
      row.querySelector('[data-down]').disabled = judge.chief || index === judges.length - 1;
      row.querySelector('[data-up]').onclick = () => {const moved=judges.splice(index,1)[0];judges.splice(index-1,0,moved);paint();};
      row.querySelector('[data-down]').onclick = () => {const moved=judges.splice(index,1)[0];judges.splice(index+1,0,moved);paint();};
      list.appendChild(row);
    });
  }
  panel.querySelector('[data-save-order]').onclick = async () => {
    const body = new URLSearchParams({_csrf: csrf, competition_id: competitionId, data_mode: new URLSearchParams(location.search).get('data_mode') || 'real'});
    judges.forEach((judge) => body.append('judge_ids[]', judge.id));
    const status = panel.querySelector('[data-status]');
    status.textContent = 'Saving…';
    try {const response=await fetch('judge-order.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body});const data=await response.json();if(!response.ok||!data.ok)throw new Error(data.error||'Save failed.');status.textContent='Saved. Reloading…';location.reload();}
    catch(error){status.textContent=error.message;status.className='ms-2 small text-danger';}
  };
  paint();
})();
