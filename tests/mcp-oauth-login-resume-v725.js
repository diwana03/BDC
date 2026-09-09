const fs=require('fs');
const assert=(value,message)=>{if(!value)throw new Error(message)};
const read=file=>fs.readFileSync(file,'utf8');

const authorize=read('mcp/oauth/authorize.php');
const admin=read('admin/index.php');
const service=read('app/Services/McpOAuthService.php');
const version=JSON.parse(read('VERSION.json'));

assert(authorize.includes('McpOAuthService::rememberAuthorizationRequest($authorizationParams)'), 'authorization request must be saved server-side before login');
assert(authorize.includes("url('admin/?mcp_oauth_resume=1')"), 'authorization must redirect into the real admin login');
assert(!authorize.includes('Open BDC Admin login'), 'authorization must not leave users on the old dead-end login page');
assert(authorize.includes("if(!Auth::check())"), 'authorization must distinguish a missing login from a forbidden role');
assert(authorize.includes("http_response_code(403)"), 'a logged-in non-Super-Admin must be forbidden without a login loop');
assert(authorize.includes('McpOAuthService::forgetAuthorizationRequest()'), 'authorization resume state must be cleared after successful re-entry');

assert(admin.includes('use App\\Services\\McpOAuthService;'), 'admin login must load the OAuth resume service');
assert((admin.match(/bdcAdminLoginDestination\(\)/g)||[]).length>=3, 'password and 2FA success must resume OAuth');
assert(admin.includes("isset($_GET['mcp_oauth_resume'])"), 'an already authenticated admin must resume OAuth immediately');

assert(service.includes("private const AUTHORIZATION_RESUME_SESSION_KEY='bdc_mcp_oauth_authorization_resume'"), 'OAuth resume must use a dedicated session key');
assert(service.includes('private const AUTHORIZATION_RESUME_TTL=600'), 'OAuth resume state must expire promptly');
assert(service.includes("absolute_url('mcp/oauth/authorize.php')"), 'resume destination must be reconstructed as the same-origin authorization endpoint');
assert(service.includes("PHP_QUERY_RFC3986"), 'resume parameters must be encoded safely');
assert(!admin.includes('return_to'), 'admin login must not accept a user-controlled return URL');

assert(version.version==='2.3.6-dev725'&&version.build===3431, 'release metadata mismatch');
console.log('MCP OAuth login resume v725 checks passed');
