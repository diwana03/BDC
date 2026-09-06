const fs=require('fs');
const auth=fs.readFileSync('app/Core/Auth.php','utf8');
const login=fs.readFileSync('app/Views/auth/login.php','utf8');
if(!auth.includes("self::rememberDevice((int)$user['id']);"))throw new Error('30-day trusted device is not created after 2FA');
if(auth.includes("$row['user_agent_hash'],hash('sha256',(string)($_SERVER['HTTP_USER_AGENT']??''))"))throw new Error('Trusted login is still invalidated by exact User-Agent changes');
if(!auth.includes("'remember_days'=>30"))throw new Error('30-day auth audit marker missing');
if(!login.includes('stay signed in for 30 days'))throw new Error('30-day login policy is not visible at 2FA');
if(login.includes('name="remember_device"'))throw new Error('30-day persistence must not depend on an unchecked checkbox');
console.log('admin remember login v650: PASS');
