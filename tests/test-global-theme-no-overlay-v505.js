const fs = require('fs');
const assert = require('assert');

const css = fs.readFileSync('public/assets/css/bdc-theme.css', 'utf8');
const branding = fs.readFileSync('public/js/bdc-global-branding.js', 'utf8');
const bootstrap = fs.readFileSync('bootstrap.php', 'utf8');

assert(css.includes('.bdc-theme-control{position:static;z-index:auto;'), 'base theme control must never float over page content');
assert(css.includes('.bdc-theme-control-inline{position:static!important;'), 'inline theme controls must stay in header flow');
assert(css.includes('.bdc-theme-fallback-bar{position:relative;z-index:1040;'), 'fallback theme bar must reserve normal page space');
assert(css.includes('overflow:hidden;background:linear-gradient'), 'fallback bar must contain theme-control edges and shadows');
assert(!css.includes('.bdc-theme-control{position:fixed;'), 'fixed desktop theme overlay must be removed');
assert(!css.includes('bottom:8px'), 'fixed mobile theme overlay must be removed');
assert(branding.includes('box-shadow:none!important'), 'navbar theme control must not cast a protruding overlay shadow');
const brandingCache=/bdc-global-branding\.js\?v=(\d+)/.exec(bootstrap);assert(brandingCache&&Number(brandingCache[1])>=505,'global branding cache key must remain v505 or newer');

console.log('Global theme no-overlay v505 passed.');
