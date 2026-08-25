const fs=require('fs');
const runner=fs.readFileSync('app/Services/MigrationRunner.php','utf8');
const version=JSON.parse(fs.readFileSync('VERSION.json','utf8'));
for(const token of [
 '20260825_0300_restore_manual_special_categories',
 '20260825_0400_special_category_backup_recovery',
 '5da67a98bba0a16199ce6c0e5dea2d65729b9efd2104a68ef005edf3db4cea41',
 'a9c44cb2dd10923afcedfaa4ba364e56b73f33f74f4c2e82a20fc9fc75e467da',
])if(!runner.includes(token))throw new Error('Migration checksum compatibility missing '+token);
if(!runner.includes('hash_equals($storedChecksum, $checksum)'))throw new Error('Strict checksum verification was removed');
if(!/^2\.3\.3-dev\d+$/.test(version.version)||version.build<3107)throw new Error('VERSION.json predates dev401 build 3107');
console.log('PASS Special Category migration checksum repair v401');
