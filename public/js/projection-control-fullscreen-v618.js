(() => {
  'use strict';
  const button = document.querySelector('[data-fullscreen-control]');
  if (!button) return;
  const targetDocument = window.parent !== window && window.parent.document
    ? window.parent.document
    : document;
  button.addEventListener('click', async () => {
    try {
      if (targetDocument.fullscreenElement) await targetDocument.exitFullscreen();
      else if (targetDocument === document) await document.documentElement.requestFullscreen();
      else await targetDocument.documentElement.requestFullscreen();
    } catch {}
  });
  targetDocument.addEventListener('fullscreenchange', () => {
    button.textContent = targetDocument.fullscreenElement ? 'Exit Full Screen' : 'Full Screen';
  });
})();
