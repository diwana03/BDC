(function () {
  'use strict';
  const rowsFor = (wrap) => Array.from(wrap.querySelectorAll(':scope > .judge-row'));
  const chiefRadio = (row) => row.querySelector('input[type="radio"][name="chief_index"], input[type="radio"][name="final_chief_key"]');

  function refresh(wrap) {
    const initial = rowsFor(wrap);
    const chiefRow = initial.find((row) => chiefRadio(row)?.checked);
    if (chiefRow && initial[0] !== chiefRow) wrap.insertBefore(chiefRow, initial[0]);
    rowsFor(wrap).forEach((row, index, rows) => {
      const radio = chiefRadio(row);
      const chief = Boolean(radio?.checked);
      row.classList.toggle('border', chief);
      row.classList.toggle('border-warning', chief);
      row.classList.toggle('bg-warning-subtle', chief);
      const standardLabel = row.querySelector('strong');
      const finalLabel = row.querySelector('.final-judge-number');
      if (standardLabel) standardLabel.textContent = chief ? 'Judge 1 · Chief' : 'Judge ' + (index + 1);
      if (finalLabel) finalLabel.textContent = chief ? 'Judge 1 · Chief' : 'Judge ' + (index + 1);
      if (radio?.name === 'chief_index') radio.value = String(index);
      const controls = row.querySelector('[data-judge-order-controls]');
      if (!controls) return;
      controls.querySelector('[data-move="up"]').disabled = chief || index <= 1;
      controls.querySelector('[data-move="down"]').disabled = chief || index === rows.length - 1;
      controls.querySelector('[data-pinned]').classList.toggle('d-none', !chief);
      controls.querySelectorAll('[data-move]').forEach((button) => button.classList.toggle('d-none', chief));
    });
  }

  function addControls(row) {
    if (row.querySelector('[data-judge-order-controls]')) return;
    const controls = document.createElement('span');
    controls.dataset.judgeOrderControls = '1';
    controls.className = 'd-inline-flex gap-1 align-items-center ms-2';
    controls.innerHTML = '<button type="button" class="btn btn-sm btn-outline-secondary" data-move="up" aria-label="Move judge up">↑</button><button type="button" class="btn btn-sm btn-outline-secondary" data-move="down" aria-label="Move judge down">↓</button><span class="badge text-bg-warning d-none" data-pinned>Chief · Pinned first</span>';
    const target = row.querySelector('.col-md-2:last-child, .input-group-text:last-of-type') || row;
    target.appendChild(controls);
    controls.addEventListener('click', (event) => {
      const button = event.target.closest('[data-move]');
      if (!button) return;
      const wrap = row.parentElement;
      const rows = rowsFor(wrap);
      const index = rows.indexOf(row);
      if (button.dataset.move === 'up' && index > 1) wrap.insertBefore(row, rows[index - 1]);
      if (button.dataset.move === 'down' && index >= 1 && index < rows.length - 1) wrap.insertBefore(rows[index + 1], row);
      refresh(wrap);
    });
  }

  function install(wrap) {
    if (!wrap || wrap.dataset.judgeOrderReady === '1') return;
    wrap.dataset.judgeOrderReady = '1';
    rowsFor(wrap).forEach(addControls);
    wrap.addEventListener('change', (event) => {
      if (event.target.matches('input[type="radio"]')) refresh(wrap);
    });
    new MutationObserver(() => {
      rowsFor(wrap).forEach(addControls);
      refresh(wrap);
    }).observe(wrap, {childList: true});
    refresh(wrap);
  }

  function boot() {
    install(document.getElementById('judgesWrap'));
    install(document.getElementById('finalJudgesWrap'));
    document.querySelectorAll('#judgesForm, #finalJudgesForm, #judge-setup form').forEach((form) => {
      form.addEventListener('submit', () => {
        const wrap = form.querySelector('#judgesWrap, #finalJudgesWrap');
        if (wrap) refresh(wrap);
      });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
