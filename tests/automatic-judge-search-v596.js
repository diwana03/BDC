const fs=require('fs');
const assert=require('assert');
const source=fs.readFileSync('admin/scoring/automatic-common-setup.php','utf8');
assert(source.includes("array_filter([$name,$code,$country!==''?$country.' · '.$name:''])"),'Judge suggestions must include name, Judge ID and country aliases');
assert(source.includes('data-judge-name='),'Each alias must retain the canonical Judge name');
assert(source.includes('normalise(item.name)'),'Alias selection must resolve to the canonical Judge profile');
assert(source.includes('directory.value=match?match.id:"0"'),'Selected aliases must retain the Judge Database ID');
console.log('Automatic Judge multi-field search v596 tests passed.');
