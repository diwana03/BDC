(function () {
  'use strict';

  var STORAGE_KEY = 'bdc-theme-preference';
  var OPTIONS = ['light', 'dark', 'system'];
  var script = document.currentScript;
  var assetBase = script && script.src ? script.src.replace(/\/js\/bdc-theme(?:\.min)?\.js(?:\?.*)?$/, '') : '';

  if (!document.querySelector('link[data-bdc-theme-styles], link[href*="/css/bdc-theme.css"]')) {
    var stylesheet = document.createElement('link');
    stylesheet.rel = 'stylesheet';
    stylesheet.href = assetBase + '/css/bdc-theme.css?v=341';
    stylesheet.dataset.bdcThemeStyles = '1';
    document.head.appendChild(stylesheet);
  }

  function preference() {
    try {
      var saved = localStorage.getItem(STORAGE_KEY);
      return OPTIONS.indexOf(saved) >= 0 ? saved : 'light';
    } catch (error) {
      return 'light';
    }
  }

  function effective(value) {
    return value === 'system'
      ? (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
      : value;
  }

  function apply(value, notify) {
    var selected = OPTIONS.indexOf(value) >= 0 ? value : 'light';
    var resolved = effective(selected);
    document.documentElement.dataset.bdcThemePreference = selected;
    document.documentElement.dataset.bdcTheme = resolved;
    document.documentElement.style.colorScheme = resolved;
    document.querySelectorAll('[data-bdc-theme-option]').forEach(function (button) {
      var active = button.dataset.bdcThemeOption === selected;
      button.classList.toggle('active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    var label = document.querySelector('[data-bdc-theme-current]');
    if (label) label.textContent = selected.charAt(0).toUpperCase() + selected.slice(1);
    if (notify && channel) channel.postMessage(selected);
  }

  function save(value) {
    try { localStorage.setItem(STORAGE_KEY, value); } catch (error) {}
    apply(value, true);
  }

  function buildControl() {
    if (window.top !== window || document.querySelector('.bdc-theme-control')) return;
    var control = document.createElement('div');
    control.className = 'bdc-theme-control';
    control.setAttribute('aria-label', 'Appearance');
    control.innerHTML = '<span class="bdc-theme-label">Theme</span>' + OPTIONS.map(function (option) {
      var icon = option === 'light' ? '☀' : option === 'dark' ? '☾' : '◐';
      return '<button type="button" data-bdc-theme-option="' + option + '" aria-pressed="false"><span aria-hidden="true">' + icon + '</span><span>' + option.charAt(0).toUpperCase() + option.slice(1) + '</span></button>';
    }).join('');
    var adminActions = document.querySelector('.admin-topbar-actions-v203');
    var judgeHeaderMeta = document.querySelector('.judge-premium-header .judge-header-meta');
    if (adminActions) {
      control.classList.add('bdc-theme-control-inline');
      adminActions.insertBefore(control, adminActions.firstChild);
    } else if (judgeHeaderMeta) {
      control.classList.add('bdc-theme-control-inline', 'bdc-theme-control-judge');
      judgeHeaderMeta.appendChild(control);
    } else {
      document.body.appendChild(control);
    }
    control.querySelectorAll('[data-bdc-theme-option]').forEach(function (button) {
      button.addEventListener('click', function () { save(button.dataset.bdcThemeOption); });
    });
    apply(preference(), false);
  }

  var channel = 'BroadcastChannel' in window ? new BroadcastChannel('bdc-theme') : null;
  if (channel) channel.onmessage = function (event) { apply(event.data, false); };
  addEventListener('storage', function (event) { if (event.key === STORAGE_KEY) apply(preference(), false); });
  var scheme = matchMedia('(prefers-color-scheme: dark)');
  var onScheme = function () { if (preference() === 'system') apply('system', false); };
  if (scheme.addEventListener) scheme.addEventListener('change', onScheme); else scheme.addListener(onScheme);

  apply(preference(), false);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', buildControl);
  else buildControl();
})();
