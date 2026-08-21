(function () {
  'use strict';
  if (window.__bdcGlobalBrandingLoaded) return;
  window.__bdcGlobalBrandingLoaded = true;

  const logoUrl = String(window.BDC_OFFICIAL_LOGO_URL || '');
  if (!logoUrl) return;

  const style = document.createElement('style');
  style.id = 'bdc-global-branding-style';
  style.textContent = `
    .bdc-official-logo-shell{display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto;background:#fff!important;border:1px solid rgba(15,23,42,.14);border-radius:12px;padding:5px;box-shadow:0 4px 14px rgba(15,23,42,.16)}
    .bdc-official-logo{display:block!important;width:46px!important;height:46px!important;max-width:46px!important;max-height:46px!important;object-fit:contain!important;background:#fff!important;border:0!important;border-radius:8px!important;margin:0!important}
    .bdc-brand-navbar{gap:.65rem}
    .bdc-global-brand-floating{position:fixed;top:10px;left:10px;z-index:2147483000;pointer-events:none}
    .bdc-global-brand-floating .bdc-official-logo{width:54px!important;height:54px!important;max-width:54px!important;max-height:54px!important}
    @media(max-width:640px){.bdc-official-logo{width:38px!important;height:38px!important;max-width:38px!important;max-height:38px!important}.bdc-global-brand-floating{top:6px;left:6px}.bdc-global-brand-floating .bdc-official-logo{width:42px!important;height:42px!important;max-width:42px!important;max-height:42px!important}}
    @media print{.bdc-global-brand-floating{position:absolute}.bdc-official-logo-shell{box-shadow:none;border-color:#ddd}}
  `;
  document.head.appendChild(style);

  function shell(image) {
    image.src = logoUrl;
    image.alt = 'Bachata Dance Council logo';
    image.classList.add('bdc-official-logo');
    image.removeAttribute('width');
    image.removeAttribute('height');
    if (image.parentElement?.classList.contains('bdc-official-logo-shell')) return image.parentElement;
    const wrap = document.createElement('span');
    wrap.className = 'bdc-official-logo-shell';
    image.parentNode?.insertBefore(wrap, image);
    wrap.appendChild(image);
    return wrap;
  }

  const existing = document.querySelector('img[src*="bdc-logo"]');
  if (existing) {
    shell(existing);
    return;
  }

  const image = document.createElement('img');
  const wrap = shell(image);
  const brandTarget = document.querySelector('.navbar-brand, .admin-topbar-brand-v203, .portal-brand, header.top .wrap');
  if (brandTarget) {
    brandTarget.classList.add('bdc-brand-navbar');
    brandTarget.insertBefore(wrap, brandTarget.firstChild);
    return;
  }

  wrap.classList.add('bdc-global-brand-floating');
  wrap.setAttribute('aria-label', 'Bachata Dance Council');
  document.body.insertBefore(wrap, document.body.firstChild);
})();
