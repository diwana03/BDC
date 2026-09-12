const fs = require('fs');

const projector = fs.readFileSync('admin/dance-cup/projector.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

assert(projector.includes('.view{width:100%;height:100%;min-height:0;overflow:hidden;display:grid'), 'projector view does not contain overflowing screen content');
assert(projector.includes('.screen-content{width:100%;height:auto;min-height:0;'), 'screen content still consumes the full stage in addition to the heading');
assert(!projector.includes('.screen-content{width:100%;height:100%;'), 'legacy overflowing content height remains');
assert(projector.includes('.contestant-grid{\n  min-height:0!important;\n  overflow:hidden!important;'), 'contestant grid is not bounded inside its assigned row');
assert(projector.includes('.contestant-grid.rows-2{grid-template-rows:repeat(2,minmax(0,1fr))!important}'), 'five by two contestant layout changed');
assert(version.version.startsWith('2.3.6-dev') && version.build >= 3439, 'release metadata is older than dev733');

console.log('Dance Cup two row fit v733 regression checks passed');
