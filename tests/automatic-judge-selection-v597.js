const fs=require('fs');
const assert=require('assert');
const source=fs.readFileSync('admin/scoring/automatic-common-setup.php','utf8');
assert(source.includes("implode(' · ',array_filter([$name,$code,$country]))"),'Each judge must have one searchable name, ID and country value');
assert(source.includes('data-judge-name='),'The combined result must retain the canonical judge name');
assert(!source.includes('foreach(array_values(array_unique(array_filter([$name,$code'), 'A judge must not be duplicated as separate aliases');
assert(!source.includes('list.replaceChildren()'),'Typing must not rebuild and interrupt the open Judge suggestion list');
assert(source.includes('input.value=match.name'),'Selection must resolve to the canonical judge name');
console.log('Automatic Judge selection v597 tests passed.');
