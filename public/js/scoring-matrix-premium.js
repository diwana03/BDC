(() => {
  const markClasses = ['matrix-mark-yes', 'matrix-mark-a1', 'matrix-mark-a2', 'matrix-mark-a3', 'matrix-mark-empty'];

  function decorate() {
    document.querySelectorAll('.live-role-scroll tbody tr, .test-role-scroll tbody tr').forEach(row => {
      row.querySelectorAll('td').forEach((cell, index) => {
        if (index < 2) return;
        cell.classList.remove(...markClasses);
        const value = cell.textContent.trim().toLowerCase();
        if (value === 'yes') cell.classList.add('matrix-mark-yes');
        else if (/^a[123]$/.test(value)) cell.classList.add('matrix-mark-' + value);
        else if (value === '—' || value === '-') cell.classList.add('matrix-mark-empty');
      });
    });
  }

  new MutationObserver(decorate).observe(document.documentElement, { childList: true, subtree: true });
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', decorate, { once: true });
  else decorate();
})();
