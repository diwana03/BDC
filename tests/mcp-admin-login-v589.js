const fs=require('fs');
const assert=require('assert');
const authorize=fs.readFileSync('mcp/oauth/authorize.php','utf8');

assert(authorize.includes("url('admin/?mcp_oauth_resume=1')"),'OAuth authorization must redirect through the real BDC Admin login entry');
assert(!authorize.includes("url('login')"),'OAuth authorization must not link to the nonexistent public login route');
assert(authorize.includes('McpOAuthService::rememberAuthorizationRequest'),'OAuth authorization must retain the validated request across login');
console.log('MCP Super Admin login route checks passed');
