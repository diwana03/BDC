const fs=require('fs');
const read=p=>fs.readFileSync(p,'utf8');
const assert=(ok,msg)=>{if(!ok)throw new Error(msg)};
const mcp=read('app/Services/BdcMcpService.php');
const profiles=read('app/Services/ProfileIntegrationService.php');
const endpoint=read('mcp/index.php');

for(const token of ['stage_competitor_photo_update','submitMcpCompetitorPhotoBatch','photo_base64','photo_mime','photo_name'])assert(mcp.includes(token),'MCP photo staging missing '+token);
for(const token of ["'operation'=>'photo_replace'","'entity_type'=>'competitor'","'source_system'=>'chatgpt_mcp'"])assert(mcp.includes(token),'MCP photo payload is not narrowly scoped: '+token);
assert(mcp.includes("'destructiveHint'=>false")&&mcp.includes("'idempotentHint'=>true"),'MCP photo tool safety annotations missing');
assert(endpoint.includes("'stage_competitor_photo_update'")&&endpoint.includes('McpOAuthService::STAGE_SCOPE'),'MCP photo tool must require stage scope');
assert(endpoint.includes('22*1024*1024'),'MCP endpoint must allow an encoded 15 MB photo plus JSON overhead');
for(const token of ['submitMcpCompetitorPhotoBatch','The MCP photo tool may stage competitor photos only.','Competitor photo replacement contains unsupported fields.','Competitor identity changed after review submission.','original_photo_url=:original'])assert(profiles.includes(token),'Approval-gated competitor photo protection missing '+token);
assert(profiles.includes("if(($p['operation']??'upsert')==='photo_replace')"),'Competitor approval path must isolate photo replacement');
console.log('MCP competitor photo staging checks passed');
