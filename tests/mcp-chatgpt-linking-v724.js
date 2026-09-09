const fs=require('fs');
const assert=(value,message)=>{if(!value)throw new Error(message)};
const read=file=>fs.readFileSync(file,'utf8');

const endpoint=read('mcp/index.php');
const tools=read('app/Services/BdcMcpService.php');
const version=JSON.parse(read('VERSION.json'));

const initialize=endpoint.indexOf("if($method==='initialize')");
const toolList=endpoint.indexOf("if($method==='tools/list')");
const database=endpoint.indexOf('$pdo=Database::connection()');
assert(initialize!==-1&&initialize<database,'initialize must be available before OAuth/database authentication');
assert(toolList!==-1&&toolList<database,'tools/list must expose security metadata before account linking');
assert(endpoint.includes("'_meta'=>['mcp/www_authenticate'=>[$challenge]]"),'protected tool failure must return the ChatGPT OAuth challenge metadata');
assert(endpoint.includes("absolute_url('mcp/.well-known/oauth-protected-resource/')"),'OAuth challenge must advertise the working protected-resource metadata URL');
assert(endpoint.includes("$challenge='Bearer resource_metadata="),'OAuth challenge must use the Bearer resource_metadata scheme');
assert(endpoint.indexOf("if($method==='tools/call')")>database,'protected tool execution must remain behind OAuth authentication');

for(const name of ['list_event_rounds','list_event_roster','stage_competitor_removals','stage_competitor_additions']){
    const start=tools.indexOf("'name'=>'"+name+"'");
    const next=tools.indexOf("['name'=>'",start+10);
    const block=tools.slice(start,next===-1?undefined:next);
    assert(block.includes("'securitySchemes'=>[['type'=>'oauth2'"),name+' must declare OAuth securitySchemes');
}

assert(/^2\.3\.6-dev(?:724|725)$/.test(version.version)&&version.build>=3430,'release metadata must retain or follow the dev724 connector-linking release');
console.log('MCP ChatGPT linking v724 checks passed');
