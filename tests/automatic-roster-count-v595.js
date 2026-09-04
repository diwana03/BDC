const fs=require('fs');
const assert=require('assert');
const source=fs.readFileSync('admin/scoring/automatic-common-setup.php','utf8');
assert(source.includes("count($entries[$role])"),'Automatic role headers must show current roster counts');
assert(source.includes('rounded-pill bg-white text-'),'Automatic counts must remain visible on dark role headers');
assert(source.includes("foreach(['leader'=>['Leaders','primary'],'follower'=>['Followers','danger']]"),'Both Automatic roles must use the counted header');
console.log('Automatic roster count v595 tests passed.');
