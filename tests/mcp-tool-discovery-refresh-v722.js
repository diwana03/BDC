const fs = require('fs');
const assert = (value, message) => { if (!value) throw new Error(message); };

const endpoint = fs.readFileSync('mcp/index.php', 'utf8');
const service = fs.readFileSync('app/Services/BdcMcpService.php', 'utf8');
const version = JSON.parse(fs.readFileSync('VERSION.json', 'utf8'));

assert(endpoint.includes("'capabilities'=>['tools'=>['listChanged'=>true]]"), 'MCP must advertise tool-list changes');
assert(service.includes("'name'=>'stage_competitor_removals'"), 'withdrawal tool must remain published');
assert(service.includes("'name'=>'stage_competitor_bib_updates'"), 'bib amendment tool must remain published');
assert(version.version === '2.3.6-dev722' && version.build === 3428, 'release metadata mismatch');

console.log('MCP tool discovery refresh v722 checks passed');
