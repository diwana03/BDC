const fs=require('fs');
const assert=require('assert');
const authorize=fs.readFileSync('mcp/oauth/authorize.php','utf8');

assert(authorize.includes("url('admin/')"),'OAuth authorization must link to the real BDC Admin login entry');
assert(!authorize.includes("url('login')"),'OAuth authorization must not link to the nonexistent public login route');
assert(authorize.includes('Open BDC Admin login'),'OAuth authorization must label the privileged login clearly');
console.log('MCP Super Admin login route checks passed');
