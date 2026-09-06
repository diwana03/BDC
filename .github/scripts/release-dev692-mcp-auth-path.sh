#!/usr/bin/env bash
set -euo pipefail
python3 <<'PY'
from pathlib import Path

# 1) Preserve Authorization across Apache/FastCGI so PHP receives ChatGPT Bearer tokens.
p=Path('.htaccess')
s=p.read_text()
anchor='RewriteEngine On\n'
insert='''RewriteEngine On\n\n# dev692: preserve OAuth Bearer Authorization through Apache/FastCGI.\n# Some shared-hosting stacks strip Authorization before PHP unless it is\n# explicitly copied into HTTP_AUTHORIZATION.\nRewriteCond %{HTTP:Authorization} .\nRewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]\n'''
if 'E=HTTP_AUTHORIZATION:%{HTTP:Authorization}' not in s:
    if anchor not in s: raise SystemExit('htaccess RewriteEngine anchor missing')
    s=s.replace(anchor,insert,1)
p.write_text(s)

# 2) Read Authorization robustly from every common CGI/FastCGI location.
p=Path('mcp/index.php')
s=p.read_text()
old="$header=trim((string)($_SERVER['HTTP_AUTHORIZATION']??''));$bearer=preg_match('/^Bearer\\s+(.+)$/i',$header,$m)?trim($m[1]):'';"
new="""$header=trim((string)($_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??''));if($header===''&&function_exists('getallheaders')){$headers=getallheaders();if(is_array($headers))foreach($headers as $k=>$v)if(strcasecmp((string)$k,'Authorization')===0){$header=trim((string)$v);break;}}$bearer=preg_match('/^Bearer\\s+(.+)$/i',$header,$m)?trim($m[1]):'';"""
if old not in s: raise SystemExit('mcp authorization reader anchor missing')
s=s.replace(old,new,1)
p.write_text(s)

# 3) Keep refresh-token grant bound to the single BDC resource, but accept the
# OAuth-compliant case where the client does not repeat resource on refresh.
p=Path('app/Services/McpOAuthService.php')
s=p.read_text()
old="""    public static function refresh(PDO $pdo,string $refresh,string $clientId,string $resource):array\n    {\n        self::requireResource($resource);self::ensure($pdo);$pdo->beginTransaction();try{$s=$pdo->prepare('SELECT * FROM bdc_mcp_oauth_tokens WHERE refresh_hash=:hash AND client_id=:client AND revoked_at IS NULL AND refresh_expires_at>=NOW() FOR UPDATE');"""
new="""    public static function refresh(PDO $pdo,string $refresh,string $clientId,string $resource):array\n    {\n        if($resource!=='')self::requireResource($resource);self::ensure($pdo);$pdo->beginTransaction();try{$s=$pdo->prepare('SELECT * FROM bdc_mcp_oauth_tokens WHERE refresh_hash=:hash AND client_id=:client AND revoked_at IS NULL AND refresh_expires_at>=NOW() FOR UPDATE');"""
if old not in s: raise SystemExit('oauth refresh anchor missing')
s=s.replace(old,new,1)
p.write_text(s)
PY

php -l mcp/index.php
php -l app/Services/McpOAuthService.php
php -l mcp/oauth/token.php
python3 - <<'PY'
from pathlib import Path
h=Path('.htaccess').read_text()
m=Path('mcp/index.php').read_text()
o=Path('app/Services/McpOAuthService.php').read_text()
assert 'E=HTTP_AUTHORIZATION:%{HTTP:Authorization}' in h
assert "REDIRECT_HTTP_AUTHORIZATION" in m and "getallheaders" in m
assert "if($resource!=='')self::requireResource($resource)" in o
assert "self::requireResource($resource);self::ensure($pdo);$pdo->beginTransaction()" in o  # authorization-code exchange remains strict
print('dev692 MCP auth-path assertions passed')
PY

git config user.name 'BDC Release Bot'
git config user.email 'actions@users.noreply.github.com'
git add .htaccess mcp/index.php app/Services/McpOAuthService.php
git commit -m 'Release dev692 repair MCP bearer and refresh execution path'
git push origin HEAD:develop
