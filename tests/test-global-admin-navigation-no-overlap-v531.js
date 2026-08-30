const assert = require('assert');
const fs = require('fs');

const bootstrap = fs.readFileSync('bootstrap.php', 'utf8');
const sheet = fs.readFileSync('admin/dance-cup/judge-sheet.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

for (const marker of [
  '.bdc-universal-admin-nav-inline{position:static',
  '.bdc-universal-admin-nav-bar{position:relative',
  "var pageToolbar=document.querySelector('.toolbar')",
  "pageToolbar.appendChild(controls)",
  "document.body.insertBefore(navigationBar,document.body.firstChild)",
]) assert(bootstrap.includes(marker), `Missing global non-overlap marker: ${marker}`);

assert(!bootstrap.includes("controls.classList.add('bdc-universal-admin-nav-floating')"), 'Universal navigation must never use the floating overlay');
assert(bootstrap.includes("preg_match('#/admin/dance-cup/projector(?:-launch)?\\.php$#i', $bdcRequestPath) !== 1"), 'Audience projector routes must be excluded before navigation injection');
assert(bootstrap.includes("document.body.classList.contains('dc-projector-presentation')"), 'Presentation body must retain a client-side navigation safety guard');
assert(sheet.includes('flex-wrap:wrap;min-height:58px'), 'Detailed result toolbar must wrap without covering actions');
assert.strictEqual(version.version, '2.3.3-dev536');
assert.strictEqual(version.build, 3242);

console.log('OK: universal admin navigation reserves layout space and cannot overlap page controls');
