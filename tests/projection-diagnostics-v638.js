const fs=require('node:fs');
const {execFileSync}=require('node:child_process');
const assert=require('node:assert/strict');

const service=fs.readFileSync('app/Services/ProjectionDiagnosticsService.php','utf8');
const mcp=fs.readFileSync('app/Services/BdcMcpService.php','utf8');
const endpoint=fs.readFileSync('mcp/index.php','utf8');
const docs=fs.readFileSync('docs/mcp-connector.md','utf8');
const release=fs.readFileSync('RELEASE-v2.3.3-dev638.md','utf8');
const version=JSON.parse(fs.readFileSync('VERSION.json','utf8'));

for(const marker of [
  "'name'=>'diagnose_projection'",
  "ProjectionDiagnosticsService::diagnose($pdo,$a)",
  "'event_system'=>['type'=>'string','enum'=>['jack_jill','dance_cup']]",
  "'data_mode'=>['type'=>'string','enum'=>['test','live']]",
  "'readOnlyHint'=>true",
  "McpOAuthService::READ_SCOPE"
]) assert.ok(mcp.includes(marker),'MCP projection diagnostics integration missing: '+marker);

for(const marker of [
  'bdc_test_scoring_entries','bdc_scoring_entries',
  "'bdc_test_dance_cup'","'bdc_dance_cup'","$prefix.'_entries'",
  'missing_bibs','duplicate_bibs','invalid_countries','missing_photos',
  'judge_profiles','flights','page_bounds','result_reveal_lock',
  'runtime_file_','limitations'
]) assert.ok(service.includes(marker),'projection diagnostic coverage missing: '+marker);

assert.ok(!/->(?:exec|query)\s*\(/.test(service),'diagnostic service must not execute direct write-capable PDO methods');
assert.ok(!/\b(?:INSERT|UPDATE|DELETE|ALTER|CREATE|REPLACE|TRUNCATE)\b\s+/i.test(service),'diagnostic service must contain SELECT queries only');
assert.ok(!service.includes('token_value')&&!service.includes('access_token'),'diagnostics must not read or return projection tokens');
assert.ok(endpoint.includes("$versionManifest")&&endpoint.includes("'version'=>$serverVersion"),'MCP handshake must report VERSION.json dynamically');
assert.ok(docs.includes('diagnose_projection')&&release.includes('No schema or data writes'),'diagnostic documentation missing');
assert.equal(version.version,'2.3.3-dev638');
assert.equal(version.build,3344);

const php=[
  "require 'app/Services/ProjectionDiagnosticsService.php';",
  "class DiagnosticPDO extends PDO{public function __construct(){}}",
  "$pdo=new DiagnosticPDO();",
  "$c='App\\\\Services\\\\ProjectionDiagnosticsService';",
  "$s=$c::summarizeChecks([['status'=>'pass'],['status'=>'warning'],['status'=>'fail'],['status'=>'ignored']]);",
  "if($s!==['pass'=>1,'warning'=>1,'fail'=>1]){fwrite(STDERR,'summary mismatch');exit(1);}",
  "foreach([",
  "[['event_system'=>'invalid','data_mode'=>'test','event_id'=>1],'event_system'],",
  "[['event_system'=>'jack_jill','data_mode'=>'invalid','event_id'=>1],'data_mode'],",
  "[['event_system'=>'jack_jill','data_mode'=>'test','event_id'=>0],'event_id']",
  "] as [$input,$expected]){try{$c::diagnose($pdo,$input);fwrite(STDERR,'validation missing');exit(1);}catch(RuntimeException $e){if(!str_contains($e->getMessage(),$expected)){fwrite(STDERR,'validation mismatch');exit(1);}}}"
].join('');
execFileSync('php',['-r',php],{stdio:'inherit'});
console.log('Projection diagnostics v638 behavioral and MCP integration checks passed');
