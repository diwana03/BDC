/* BDC v2.0.26 — Heats/Semifinal live calculation and manual sort */
(() => {
  'use strict';

  const VALUE_MAP = {
    'YES': 10,
    'Y': 10,
    '1': 10,
    'A1': 4.5,
    'A2': 4.3,
    'A3': 4.2,
    '': 0
  };

  function normalizeValue(raw) {
    const value = String(raw ?? '').trim().toUpperCase();
    if (value === 'ALT1') return 'A1';
    if (value === 'ALT2') return 'A2';
    if (value === 'ALT3') return 'A3';
    return value;
  }

  function scoreValue(raw) {
    const normalized = normalizeValue(raw);
    return Object.prototype.hasOwnProperty.call(VALUE_MAP, normalized)
      ? VALUE_MAP[normalized]
      : 0;
  }

  function isScoringInput(input) {
    if (!(input instanceof HTMLInputElement)) return false;
    if (['hidden', 'submit', 'button', 'checkbox', 'radio'].includes(input.type)) return false;

    const name = (input.name || '').toLowerCase();
    const placeholder = (input.placeholder || '').toLowerCase();
    const cls = input.className || '';

    return name.includes('mark')
      || name.includes('score')
      || name.includes('judge')
      || placeholder.includes('yes')
      || placeholder.includes('a1')
      || cls.includes('score-input')
      || cls.includes('mark-input');
  }

  function findTotalCell(row) {
    const explicit = row.querySelector('[data-total], .score-total, .total-score, td.total');
    if (explicit) return explicit;

    const table = row.closest('table');
    if (!table) return null;

    const headers = [...table.querySelectorAll('thead th')];
    const totalIndex = headers.findIndex(th => th.textContent.trim().toLowerCase() === 'total');
    if (totalIndex >= 0) return row.children[totalIndex] || null;

    return null;
  }

  function findResultCell(row) {
    const explicit = row.querySelector('[data-result], .score-result, .result-status, td.result');
    if (explicit) return explicit;

    const table = row.closest('table');
    if (!table) return null;

    const headers = [...table.querySelectorAll('thead th')];
    const resultIndex = headers.findIndex(th => {
      const text = th.textContent.trim().toLowerCase();
      return text === 'result' || text === 'status';
    });

    return resultIndex >= 0 ? row.children[resultIndex] || null : null;
  }

  function calculateRow(row) {
    const inputs = [...row.querySelectorAll('input')].filter(isScoringInput);
    if (!inputs.length) return 0;

    let total = 0;
    inputs.forEach(input => {
      const normalized = normalizeValue(input.value);
      if (input.value !== normalized) input.value = normalized;
      total += scoreValue(normalized);
    });

    total = Math.round(total * 10) / 10;

    const totalCell = findTotalCell(row);
    if (totalCell) {
      totalCell.textContent = total.toFixed(1);
      totalCell.dataset.total = String(total);
    }

    row.dataset.calculatedTotal = String(total);
    return total;
  }

  function allScoreTables() {
    return [...document.querySelectorAll('table')].filter(table => {
      const text = table.textContent.toLowerCase();
      return text.includes('total') && (
        text.includes('leader')
        || text.includes('follower')
        || table.closest('[data-role]')
      );
    });
  }

  function scoringRows(table) {
    return [...table.querySelectorAll('tbody tr')].filter(row =>
      [...row.querySelectorAll('input')].some(isScoringInput)
    );
  }

  function calculateAll() {
    let rows = [];
    allScoreTables().forEach(table => {
      scoringRows(table).forEach(row => {
        calculateRow(row);
        rows.push(row);
      });
    });
    return rows;
  }

  function validateAlternates() {
    const errors = [];

    allScoreTables().forEach(table => {
      const rows = scoringRows(table);
      if (!rows.length) return;

      const maxInputs = Math.max(...rows.map(row =>
        [...row.querySelectorAll('input')].filter(isScoringInput).length
      ));

      for (let column = 0; column < maxInputs; column++) {
        const used = new Map();

        rows.forEach(row => {
          const inputs = [...row.querySelectorAll('input')].filter(isScoringInput);
          const input = inputs[column];
          if (!input) return;

          const value = normalizeValue(input.value);
          if (!['A1', 'A2', 'A3'].includes(value)) return;

          if (!used.has(value)) used.set(value, []);
          used.get(value).push(input);
        });

        used.forEach((inputs, value) => {
          if (inputs.length > 1) {
            inputs.forEach(input => input.classList.add('is-invalid'));
            errors.push(`Judge ${column + 1} has duplicate ${value}.`);
          }
        });
      }
    });

    return [...new Set(errors)];
  }

  function clearValidation() {
    document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
  }

  function sortTable(table) {
    const tbody = table.querySelector('tbody');
    if (!tbody) return;

    const rows = scoringRows(table);
    rows.sort((a, b) => {
      const totalA = Number(a.dataset.calculatedTotal || calculateRow(a));
      const totalB = Number(b.dataset.calculatedTotal || calculateRow(b));
      if (totalA !== totalB) return totalB - totalA;

      const bibA = Number((a.cells[0]?.textContent || '').replace(/\D/g, '')) || 999999;
      const bibB = Number((b.cells[0]?.textContent || '').replace(/\D/g, '')) || 999999;
      return bibA - bibB;
    });

    rows.forEach(row => tbody.appendChild(row));
  }

  function manualCalculateAndSort() {
    clearValidation();
    calculateAll();

    const errors = validateAlternates();
    if (errors.length) {
      alert(errors.join('\n'));
      return;
    }

    allScoreTables().forEach(sortTable);

    const notice = document.getElementById('bdc-heats-calc-notice');
    if (notice) {
      notice.textContent = 'Totals calculated and Leaders/Followers sorted highest to lowest. Review before Submit Scores.';
      notice.classList.remove('d-none');
    }
  }

  function addManualButton() {
    if (document.getElementById('bdc-manual-calculate-sort')) return;

    const submitButton = [...document.querySelectorAll('button, input[type="submit"]')].find(el => {
      const text = (el.textContent || el.value || '').trim().toLowerCase();
      return text.includes('submit scores');
    });

    if (!submitButton) return;

    const button = document.createElement('button');
    button.type = 'button';
    button.id = 'bdc-manual-calculate-sort';
    button.className = 'btn btn-success';
    button.textContent = 'Calculate & Sort Heats Results';
    button.addEventListener('click', manualCalculateAndSort);

    const notice = document.createElement('div');
    notice.id = 'bdc-heats-calc-notice';
    notice.className = 'alert alert-success mt-2 mb-0 d-none';

    submitButton.parentElement.insertBefore(button, submitButton);
    submitButton.parentElement.insertBefore(document.createTextNode(' '), submitButton);
    submitButton.parentElement.appendChild(notice);
  }

  function bindLiveCalculation() {
    document.addEventListener('input', event => {
      const input = event.target;
      if (!isScoringInput(input)) return;

      const normalized = normalizeValue(input.value);
      if (input.value !== normalized) input.value = normalized;

      calculateRow(input.closest('tr'));
      input.classList.remove('is-invalid');
    });

    document.addEventListener('change', event => {
      const input = event.target;
      if (!isScoringInput(input)) return;
      calculateRow(input.closest('tr'));
    });
  }

  function initialize() {
    addManualButton();
    bindLiveCalculation();
    calculateAll();

    const observer = new MutationObserver(() => {
      addManualButton();
      calculateAll();
    });

    observer.observe(document.body, {childList: true, subtree: true});
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize);
  } else {
    initialize();
  }
})();