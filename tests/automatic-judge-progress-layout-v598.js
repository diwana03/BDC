const fs=require('fs');
const assert=require('assert');
const source=fs.readFileSync('admin/scoring/judge-control.php','utf8');
assert(source.includes('.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}'),'Desktop Judge progress must use a compact two-column grid');
assert(source.includes('@media(max-width:900px){.grid{grid-template-columns:1fr}}'),'Judge progress must return to one column on smaller screens');
assert(source.includes('data-judge="<?=(int)$judge[\'id\']?>"'),'Existing judge identity binding must remain intact');
console.log('Automatic Judge progress layout v598 tests passed.');
