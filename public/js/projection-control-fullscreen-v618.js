(() => {
  'use strict';
  const button = document.querySelector('[data-fullscreen-control]');
  if (!button) return;
  button.addEventListener('click', async () => {
    try {
      if (document.fullscreenElement) await document.exitFullscreen();
      else await document.documentElement.requestFullscreen();
    } catch {}
  });
  document.addEventListener('fullscreenchange', () => {
    button.textContent = document.fullscreenElement ? 'Exit Full Screen' : 'Full Screen';
  });
})();
