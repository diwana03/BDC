'use strict';

const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '..');
const read = relativePath => fs.readFileSync(path.join(root, relativePath), 'utf8');

const controller = read('public/assets/js/bdc-theme.js');
const theme = read('public/assets/css/bdc-theme.css');
const branding = read('public/js/bdc-global-branding.js');
const bootstrap = read('bootstrap.php');

const checks = {
  'fallback toolbar is inserted before page content':
    controller.includes("fallbackBar.className = 'bdc-theme-fallback-bar'")
      && controller.includes('document.body.insertBefore(fallbackBar, document.body.firstChild)'),
  'fallback control is not fixed over navigation':
    theme.includes('.bdc-theme-fallback-bar>.bdc-theme-control{position:static'),
  'fallback toolbar reserves responsive page space':
    theme.includes('.bdc-theme-fallback-bar{position:relative')
      && theme.includes('@media(max-width:700px)')
      && theme.includes('.bdc-theme-fallback-bar{min-height:50px'),
  'premium navbar docking removes empty fallback toolbar':
    branding.split("const fallbackBar = control.closest('.bdc-theme-fallback-bar')").length - 1 === 2
      && branding.split('fallbackBar?.remove()').length - 1 === 2,
  'theme stylesheet cache is current': controller.includes('/css/bdc-theme.css?v=420'),
  'global branding cache is current': bootstrap.includes('bdc-global-branding.js?v=420'),
};

const themeEntryPoints = [
  'admin/dance-cup/automatic-setup.php',
  'admin/dance-cup/automation.php',
  'admin/dance-cup/category.php',
  'admin/dance-cup/index.php',
  'admin/dance-cup/judge-scoring.php',
  'admin/dance-cup/projection-control.php',
  'admin/dance-cup/select-mode.php',
  'admin/dance-cup/workflow.php',
  'admin/live-screen/control.php',
  'admin/live-screen/projection-workspace.php',
  'admin/scoring-tests/automatic-screen.php',
  'admin/scoring-tests/index.php',
  'admin/scoring-tests/select-mode.php',
  'admin/scoring/index.php',
  'app/Views/admin/dashboard.php',
  'judge-scoring/index.php',
  'test-judge-scoring/index.php',
];

for (const entryPoint of themeEntryPoints) {
  checks[`current cache on ${entryPoint}`] = read(entryPoint).includes('bdc-theme.js?v=420');
}
checks['current direct dashboard stylesheet cache'] = read('app/Views/admin/dashboard.php').includes('bdc-theme.css?v=420');
checks['current shared scoring stylesheet cache'] = read('public/css/scoring-premium.css').includes('bdc-theme.css?v=420');

const failed = Object.entries(checks).filter(([, passed]) => !passed).map(([label]) => label);
if (failed.length) {
  throw new Error(`Theme/navigation overlap v420 failed: ${failed.join(', ')}`);
}

console.log('Theme/navigation overlap v420 checks passed.');
