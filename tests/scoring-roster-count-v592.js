const fs=require('fs');
const assert=require('assert');

for(const file of ['admin/scoring/core.php','admin/scoring-tests/index.php']){
  const source=fs.readFileSync(file,'utf8');
  assert(source.includes("<?=count($entries['leader'])?>"),`${file} must show the live Leader roster count`);
  assert(source.includes("<?=count($entries['follower'])?>"),`${file} must show the live Follower roster count`);
}
console.log('Scoring roster count v592 tests passed.');
