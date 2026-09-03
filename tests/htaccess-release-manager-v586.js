const fs=require('fs');const assert=require('assert');
const ht=fs.readFileSync('.htaccess','utf8');
assert(!ht.split('\n').some(line=>/<FilesMatch[^>]*>.*Require\s+all\s+denied.*<\/FilesMatch>/i.test(line)),'Apache FilesMatch containers must use separate directive lines');
assert(/<FilesMatch "\^config\\\.php\$">\n\s+Require all denied\n<\/FilesMatch>/.test(ht),'Protected config FilesMatch block is malformed');
assert(!/system-release/i.test(ht),'Root routing must not intercept or special-case the Release Manager');
assert(ht.includes('RewriteCond %{REQUEST_FILENAME} !-d'),'Existing directories, including admin/system-release, must bypass the front controller');
console.log('htaccess Release Manager safety checks passed');
