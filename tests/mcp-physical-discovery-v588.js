const fs=require('fs');
const assert=require('assert');
const read=path=>fs.readFileSync(path,'utf8');
const endpoint=read('mcp/index.php');
const tools=read('app/Services/BdcMcpService.php');
const oauth=read('app/Services/McpOAuthService.php');

assert(fs.existsSync('mcp/oauth/.well-known/oauth-authorization-server/index.php'),'physical authorization-server discovery endpoint missing');
assert(fs.existsSync('mcp/.well-known/oauth-protected-resource/index.php'),'physical protected-resource discovery endpoint missing');
assert(read('mcp/oauth/.well-known/oauth-authorization-server/index.php').includes("/metadata.php"),'authorization discovery must serve metadata');
assert(read('mcp/.well-known/oauth-protected-resource/index.php').includes("/oauth/resource.php"),'resource discovery must serve resource metadata');
assert(oauth.includes("rtrim(\\absolute_url('mcp'),'/').'/'"),'canonical MCP resource must use the working trailing-slash URL');
assert(endpoint.includes('scope="bdc.events.read bdc.events.stage"'),'HTTP OAuth challenge must advertise scopes');
assert(endpoint.includes("'version'=>'2.3.3-dev588'"),'MCP server version must match release');
assert((tools.match(/'securitySchemes'=>/g)||[]).length===4,'every MCP tool must declare OAuth security schemes');
console.log('physical MCP OAuth discovery and tool security checks passed');
