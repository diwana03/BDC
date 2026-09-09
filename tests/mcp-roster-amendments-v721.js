const fs=require('fs');const assert=(value,message)=>{if(!value)throw new Error(message)};const read=file=>fs.readFileSync(file,'utf8');
const mcp=read('app/Services/BdcMcpService.php');const integration=read('app/Services/EventIntegrationService.php');const endpoint=read('mcp/index.php');const review=read('admin/integration-review/events.php');
for(const token of ["'name'=>'stage_competitor_removals'","'name'=>'stage_competitor_bib_updates'",'entry_ids','bib_number',"'remove_competitors'","'update_bibs'"])assert(mcp.includes(token),'missing MCP roster amendment contract: '+token);
for(const token of ['existingJackJillRosterChangePayload','applyExistingJackJillRosterChange','before_roster_hash','Roster changes are locked because scoring has started','would be duplicated',"entry_status='withdrawn'",'bib_number=1000000+id'])assert(integration.includes(token),'missing atomic roster amendment safety: '+token);
for(const token of ["'stage_competitor_removals'","'stage_competitor_bib_updates'"])assert(endpoint.includes(token),'roster amendment must require staging scope: '+token);
for(const token of ['$isRemoval','$isBibUpdate','Withdraw selected competitors, atomically','Amend selected bibs, atomically'])assert(review.includes(token),'missing Integration Review explanation: '+token);
assert(!integration.includes('DELETE FROM {$entries}'),'roster removal must remain recoverable and never delete entries');
const version=JSON.parse(read('VERSION.json'));assert(Number(version.version.match(/^2\.3\.6-dev(\d+)$/)?.[1]||0)>=722&&version.build>=3428,'release metadata mismatch');
console.log('MCP roster amendments v721 checks passed');
