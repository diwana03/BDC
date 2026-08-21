(function () {
  'use strict';
  if (window.__bdcGlobalBrandingLoaded) return;
  window.__bdcGlobalBrandingLoaded = true;

  const logoUrl = String(window.BDC_OFFICIAL_LOGO_URL || '');
  if (!logoUrl) return;

  const style = document.createElement('style');
  style.id = 'bdc-global-branding-style';
  style.textContent = `
    .bdc-official-logo-shell{display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto;background:#fff!important;border:1px solid rgba(15,23,42,.14);border-radius:14px;padding:5px;box-shadow:0 6px 18px rgba(15,23,42,.2)}
    .bdc-official-logo{display:block!important;width:50px!important;height:50px!important;max-width:50px!important;max-height:50px!important;object-fit:contain!important;background:#fff!important;border:0!important;border-radius:9px!important;margin:0!important}
    .bdc-brand-navbar{display:inline-flex!important;align-items:center!important;gap:.8rem!important;min-width:0;color:#fff!important;text-decoration:none!important}
    .bdc-brand-copy{display:flex;min-width:0;flex-direction:column;justify-content:center;line-height:1.05}
    .bdc-brand-title{font-size:1.02rem;font-weight:850;letter-spacing:-.01em;white-space:nowrap}
    .bdc-brand-context{margin-top:5px;color:#ead7aa;font-size:.68rem;font-weight:750;letter-spacing:.08em;text-transform:uppercase;white-space:nowrap}
    .bdc-premium-navbar{min-height:76px!important;padding:0!important;border:0!important;border-bottom:2px solid #c9a45c!important;background:linear-gradient(108deg,#101b31 0%,#182640 58%,#541c35 100%)!important;box-shadow:0 9px 24px rgba(15,23,42,.2)!important}
    .bdc-premium-navbar>.bdc-premium-navbar-inner{width:100%;max-width:1680px;min-height:76px;margin:0 auto;padding:10px clamp(16px,2.2vw,38px)!important;display:flex!important;align-items:center!important;gap:18px!important}
    .bdc-premium-navbar .bdc-premium-nav-actions{display:flex!important;align-items:center!important;justify-content:flex-end!important;gap:8px!important;margin-left:auto!important;flex-wrap:wrap}
    .bdc-premium-navbar .btn{min-height:36px;padding:.45rem .78rem;border-radius:10px!important;font-size:.78rem;font-weight:750;letter-spacing:.01em;display:inline-flex;align-items:center;justify-content:center}
    .bdc-premium-navbar .btn-outline-light{border-color:rgba(255,255,255,.44)!important;color:#fff!important;background:rgba(255,255,255,.04)!important}
    .bdc-premium-navbar .btn-outline-light:hover{border-color:#ead7aa!important;background:#f7ecd2!important;color:#541c35!important}
    .bdc-premium-navbar .btn-warning{border-color:#d5b06a!important;background:#d5b06a!important;color:#2b1d0e!important}
    .bdc-premium-navbar .bdc-theme-control-navbar{position:static!important;z-index:auto!important;margin-left:4px;background:rgba(7,10,17,.3)!important;border-color:rgba(213,176,106,.62)!important;box-shadow:inset 0 1px 0 rgba(255,255,255,.05)!important}
    .bdc-global-brand-floating{position:fixed;top:10px;left:10px;z-index:2147483000;pointer-events:none}
    .bdc-global-brand-floating .bdc-official-logo{width:54px!important;height:54px!important;max-width:54px!important;max-height:54px!important}
    @media(max-width:760px){.bdc-premium-navbar>.bdc-premium-navbar-inner{align-items:flex-start!important;min-height:68px;padding:9px 12px!important;gap:10px!important;flex-wrap:wrap}.bdc-official-logo{width:42px!important;height:42px!important;max-width:42px!important;max-height:42px!important}.bdc-brand-title{font-size:.92rem}.bdc-brand-context{display:none}.bdc-premium-navbar .bdc-premium-nav-actions{width:100%;justify-content:flex-start!important;margin-left:0!important;padding-left:56px}.bdc-premium-navbar .btn{min-height:34px;padding:.4rem .65rem}.bdc-premium-navbar .bdc-theme-control-navbar{margin-left:0!important}.bdc-global-brand-floating{top:6px;left:6px}.bdc-global-brand-floating .bdc-official-logo{width:42px!important;height:42px!important;max-width:42px!important;max-height:42px!important}}
    @media(max-width:460px){.bdc-premium-navbar .bdc-premium-nav-actions{padding-left:0}.bdc-premium-navbar .btn{flex:1 1 auto}.bdc-brand-title{font-size:.86rem}}
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

  function contextLabel() {
    const path = location.pathname.toLowerCase();
    if (path.includes('/scoring-tests')) return 'Testing Scoreboard';
    if (path.includes('/dance-cup')) return 'Dance Cup';
    if (path.includes('/live-screen')) return 'Projection Control';
    if (path.includes('/scoring')) return 'Scoring Operations';
    if (path.includes('/judges')) return 'Judge Database';
    return 'Competition Portal';
  }

  function enhanceNavbar(brandTarget) {
    const navbar = brandTarget.closest('.navbar');
    if (!navbar) return;
    navbar.classList.add('bdc-premium-navbar');
    const inner = brandTarget.parentElement;
    if (inner) {
      inner.classList.add('bdc-premium-navbar-inner');
      const actionCandidates = Array.from(inner.children).filter(function (child) { return child !== brandTarget; });
      if (actionCandidates.length) {
        const actions = actionCandidates.length === 1 && actionCandidates[0].classList.contains('d-flex')
          ? actionCandidates[0]
          : null;
        if (actions) actions.classList.add('bdc-premium-nav-actions');
        else {
          const actionWrap = document.createElement('div');
          actionWrap.className = 'bdc-premium-nav-actions';
          actionCandidates.forEach(function (child) { actionWrap.appendChild(child); });
          inner.appendChild(actionWrap);
        }
      }
    }
    const text = brandTarget.textContent.trim() || 'BDC Admin';
    brandTarget.querySelectorAll('.bdc-brand-copy').forEach(function (node) { node.remove(); });
    Array.from(brandTarget.childNodes).forEach(function (node) {
      if (node.nodeType === Node.TEXT_NODE && node.textContent.trim()) node.remove();
    });
    const copy = document.createElement('span');
    copy.className = 'bdc-brand-copy';
    copy.innerHTML = '<strong class="bdc-brand-title"></strong><small class="bdc-brand-context"></small>';
    copy.querySelector('.bdc-brand-title').textContent = text;
    copy.querySelector('.bdc-brand-context').textContent = contextLabel();
    brandTarget.appendChild(copy);
  }

  function dockThemeControl() {
    const control = document.querySelector('.bdc-theme-control');
    const actions = document.querySelector('.bdc-premium-navbar .bdc-premium-nav-actions');
    if (!control || !actions || actions.contains(control)) return;
    control.classList.add('bdc-theme-control-inline', 'bdc-theme-control-navbar');
    actions.appendChild(control);
  }

  const brandTarget = document.querySelector('.navbar-brand, .admin-topbar-brand-v203, .portal-brand, header.top .wrap');
  const existing = document.querySelector('img[src*="bdc-logo"]');
  const image = existing || document.createElement('img');
  const wrap = shell(image);
  if (brandTarget) {
    brandTarget.classList.add('bdc-brand-navbar');
    if (!brandTarget.contains(wrap)) brandTarget.insertBefore(wrap, brandTarget.firstChild);
    if (brandTarget.matches('.navbar-brand')) {
      enhanceNavbar(brandTarget);
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', dockThemeControl);
      else dockThemeControl();
    }
    return;
  }

  wrap.classList.add('bdc-global-brand-floating');
  wrap.setAttribute('aria-label', 'Bachata Dance Council');
  document.body.insertBefore(wrap, document.body.firstChild);
})();
