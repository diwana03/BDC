const fs = require('fs');
const assert = require('assert');

const projector = fs.readFileSync('admin/dance-cup/projector.php', 'utf8');
const launch = fs.readFileSync('admin/dance-cup/projector-launch.php', 'utf8');

assert(projector.includes("header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0')"), 'projector document must never retain a stale deployed layout');
assert(projector.includes("header('Pragma: no-cache')") && projector.includes("header('Expires: 0')"), 'projector needs shared-host compatibility cache headers');
assert(launch.includes("'&presentation=498'"), 'projector launch redirect must change when the presentation template changes');
console.log('Dance Cup projector cache v497 checks passed.');
