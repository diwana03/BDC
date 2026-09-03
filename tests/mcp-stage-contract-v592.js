const fs=require('fs');
const path=require('path');
const root=path.resolve(__dirname,'..');
const service=fs.readFileSync(path.join(root,'app/Services/BdcMcpService.php'),'utf8');
const mcp=fs.readFileSync(path.join(root,'mcp/index.php'),'utf8');
function assert(ok,message){if(!ok)throw new Error(message);}
assert(service.includes("'council_id'=>(string)($competitor['identity_code']??'')"),'stage must map public identity_code to integration council_id');
assert(service.includes("'bib'=>$competitor['bib_number']??0"),'stage must map public bib_number to integration bib');
assert(service.includes("'competitors'=>$normalized"),'stage must submit normalized competitors');
assert(mcp.includes("'version'=>'2.3.3-dev592'"),'MCP version must be dev592');
console.log('mcp-stage-contract-v592: ok');
